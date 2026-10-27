<?php
/**
 * actions/requests.php - document requests, students, audit trail and
 * notification bookkeeping.
 *
 * Port of the Apps Script functions submitRequest(), getStudentRequests(),
 * getOrCreateStudent_(), createRequestId_(), audit_() and sendNotification_().
 */

/* =========================================================================
   Students
   ========================================================================= */

/**
 * Find the student by e-mail, creating or refreshing the record when needed.
 * Apps Script: getOrCreateStudent_(email, name, number, type)
 *
 * @return array{studentNumber:string,name:string,email:string,studentType:string,campus:string}
 */
function get_or_create_student($email, $name = '', $number = '', $type = '')
{
    $email = normalize_email($email);
    $name = limit_text($name, 160);
    $number = limit_text($number, 40);
    $type = limit_text($type, 40);
    $now = app_now()->format('Y-m-d H:i:s');

    $row = db_one('SELECT StudentID, StudentNumber, FullName, Email, StudentType, Campus FROM students WHERE LOWER(Email) = ? LIMIT 1', [$email]);

    if ($row === null) {
        $id = short_id('STU-');
        try {
            db_exec(
                'INSERT INTO students (StudentID, StudentNumber, FullName, Email, StudentType, Campus, Program, Status, CreatedAt, UpdatedAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $id,
                    $number,
                    $name,
                    $email,
                    $type !== '' ? $type : 'Current Student',
                    'ALL',
                    '',
                    'Active',
                    $now,
                    $now,
                ]
            );
        } catch (PDOException $e) {
            // The same e-mail may have been inserted a moment ago (double click,
            // two tabs). Fall back to the existing row instead of failing.
            if (db_value('SELECT StudentID FROM students WHERE LOWER(Email) = ? LIMIT 1', [$email]) === null) {
                throw $e;
            }
        }

        $row = db_one('SELECT StudentID, StudentNumber, FullName, Email, StudentType, Campus FROM students WHERE LOWER(Email) = ? LIMIT 1', [$email]);
    } else {
        if ($name !== '') {
            db_exec('UPDATE students SET FullName = ? WHERE StudentID = ?', [$name, $row['StudentID']]);
            $row['FullName'] = $name;
        }
        if ($number !== '') {
            db_exec('UPDATE students SET StudentNumber = ? WHERE StudentID = ?', [$number, $row['StudentID']]);
            $row['StudentNumber'] = $number;
        }
        if ($type !== '') {
            db_exec('UPDATE students SET StudentType = ? WHERE StudentID = ?', [$type, $row['StudentID']]);
            $row['StudentType'] = $type;
        }
        db_exec('UPDATE students SET UpdatedAt = ? WHERE StudentID = ?', [$now, $row['StudentID']]);
    }

    if ($row === null) {
        fail('The student record could not be saved. Please try again.');
    }

    return [
        'studentNumber' => (string) $row['StudentNumber'],
        'name'          => (string) $row['FullName'],
        'email'         => (string) $row['Email'],
        'studentType'   => (string) $row['StudentType'],
        'campus'        => (string) $row['Campus'] !== '' ? (string) $row['Campus'] : 'ALL',
    ];
}

/* =========================================================================
   Audit + notifications
   ========================================================================= */

/** Apps Script: audit_(requestId, actor, action, oldValue, newValue, details) */
function audit_log($requestId, $actor, $action, $oldValue = '', $newValue = '', $details = '')
{
    try {
        db_exec(
            'INSERT INTO audit_logs (LogID, RequestID, ActorEmail, Action, OldValue, NewValue, Details, Timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                short_id('LOG-'),
                limit_text($requestId, 32),
                limit_text($actor, 190),
                limit_text($action, 60),
                (string) $oldValue,
                (string) $newValue,
                (string) $details,
                app_now()->format('Y-m-d H:i:s'),
            ]
        );
    } catch (Exception $e) {
        // The audit trail must never break a student transaction.
    }
}

/** Store an outgoing message in the notifications table (regardless of result). */
function notification_log($requestId, $email, $type, $subject, $message, $status)
{
    try {
        db_exec(
            'INSERT INTO notifications (NotificationID, RequestID, RecipientEmail, Type, Subject, Message, SentAt, Status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                short_id('NTF-'),
                limit_text($requestId, 32),
                limit_text($email, 190),
                limit_text($type, 80),
                limit_text($subject, 255),
                (string) $message,
                app_now()->format('Y-m-d H:i:s'),
                limit_text($status, 40),
            ]
        );
    } catch (Exception $e) {
        // Never block the caller because logging failed.
    }
}

/**
 * Apps Script: sendNotification_(requestId, email, type, message)
 * The Apps Script version always used the request id in the subject.
 */
function send_notification($requestId, $email, $type, $message)
{
    $subject = config('university_name') . ' Registrar ' . "\xE2\x80\xA2" . ' ' . $requestId;
    $result = mail_send($email, $subject, $message);
    notification_log($requestId, $email, $type, $subject, $message, $result['status']);
    return $result;
}

/* =========================================================================
   Request identifiers
   ========================================================================= */

/** Apps Script: createRequestId_() -> PU-REQ-2026-482913 */
function create_request_id()
{
    $year = app_now()->format('Y');
    return 'PU-REQ-' . $year . '-' . random_number(6);
}

/* =========================================================================
   Student actions
   ========================================================================= */

/**
 * Apps Script: submitRequest(session, payload)
 *
 * @return array{success:bool,requestId:string,documentName:string,fee:float,dueDate:string}
 */
function act_submitRequest($session, $payload = null)
{
    $user = require_session($session);
    $payload = is_array($payload) ? $payload : [];

    $documentCode = trim((string) ($payload['documentCode'] ?? ''));
    $purpose = limit_text($payload['purpose'] ?? '', 160);
    $releaseMethod = limit_text($payload['releaseMethod'] ?? '', 60);

    if ($documentCode === '') {
        fail('Please select a document.');
    }
    if ($purpose === '') {
        fail('Please select the purpose of your request.');
    }
    if ($releaseMethod === '') {
        fail('Please select a release method.');
    }

    $document = document_by_code($documentCode);
    if ($document === null) {
        fail('The selected document is not available.');
    }

    $now = app_now();
    $requestId = create_request_id();
    // Different students may generate the same random number: make sure the
    // identifier is free before using it.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        if (db_value('SELECT RequestID FROM requests WHERE RequestID = ? LIMIT 1', [$requestId]) === null) {
            break;
        }
        $requestId = create_request_id();
    }
    $dueDate = add_business_days($now, $document['days']);

    db_exec(
        'INSERT INTO requests (RequestID, StudentNumber, StudentEmail, StudentName, Campus, DocumentCode, Purpose, ReleaseMethod,
                               RepresentativeName, RepresentativeRelation, Status, CurrentStage, SubmittedAt, UpdatedAt, DueDate, Notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $requestId,
            limit_text($user['studentNumber'] ?? '', 40),
            limit_text($user['email'] ?? '', 190),
            limit_text($user['name'] ?? '', 160),
            limit_text($user['campus'] ?? 'ALL', 60),
            $document['code'],
            $purpose,
            $releaseMethod,
            limit_text($payload['representativeName'] ?? '', 160),
            limit_text($payload['representativeRelation'] ?? '', 80),
            'Submitted',
            'Submitted',
            $now->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
            $dueDate->format('Y-m-d H:i:s'),
            limit_text($payload['notes'] ?? '', 2000),
        ]
    );

    audit_log($requestId, $user['email'], 'REQUEST_CREATED', '', 'Submitted', $document['name']);

    send_notification(
        $requestId,
        $user['email'],
        'Request Submitted',
        'Your ' . $document['name'] . ' request has been received by the Registrar Office.'
    );

    return [
        'success'      => true,
        'requestId'    => $requestId,
        'documentName' => $document['name'],
        'fee'          => $document['fee'],
        'dueDate'      => format_date($dueDate),
    ];
}

/**
 * Apps Script: getStudentRequests(session)
 *
 * @return array<int,array<string,string>>
 */
function act_getStudentRequests($session)
{
    $user = require_session($session);

    $rows = db_all(
        'SELECT RequestID, DocumentCode, Purpose, ReleaseMethod, Status, SubmittedAt, DueDate
           FROM requests
          WHERE LOWER(StudentEmail) = ?
          ORDER BY RequestID DESC',
        [normalize_email($user['email'] ?? '')]
    );

    $requests = [];
    foreach ($rows as $row) {
        $document = document_by_code($row['DocumentCode']);
        $requests[] = [
            'requestId'     => (string) $row['RequestID'],
            'document'      => $document !== null ? $document['name'] : (string) $row['DocumentCode'],
            'purpose'       => (string) $row['Purpose'],
            'releaseMethod' => (string) $row['ReleaseMethod'],
            'status'        => (string) $row['Status'],
            'submittedAt'   => format_datetime($row['SubmittedAt']),
            'dueDate'       => format_date($row['DueDate']),
        ];
    }

    return $requests;
}
