<?php
/**
 * actions/evaluations.php - SERVE student evaluation of registrar service.
 *
 * These are the two server functions the form in index.html calls:
 *   google.script.run...getStaff()
 *   google.script.run...submitEvaluation(payload)
 *
 * Function names, argument order, return shape and error messages are kept so
 * the frontend does not need any change.
 */

/**
 * Active staff members shown in the "Who assisted you?" dropdown.
 *
 * @return array<int,array{id:string,name:string,position:string}>
 */
function act_getStaff()
{
    $rows = db_all(
        'SELECT StaffID, Name, Position
           FROM staff
          WHERE Active <> 0
          ORDER BY SortOrder, Name'
    );

    $staff = [];
    foreach ($rows as $row) {
        $staff[] = [
            'id'       => (string) $row['StaffID'],
            'name'     => (string) $row['Name'],
            'position' => (string) $row['Position'],
        ];
    }

    return $staff;
}

/** Reference shown to the student, e.g. PU-SERVE-2026-482913. */
function create_evaluation_reference()
{
    $year = app_now()->format('Y');
    return 'PU-SERVE-' . $year . '-' . random_number(6);
}

/**
 * Normalise the ratings array sent by the form.
 *
 * @param mixed $ratings
 * @return array<int,int>
 */
function normalise_ratings($ratings)
{
    if (!is_array($ratings) || count($ratings) === 0) {
        fail('Please answer all evaluation questions.');
    }
    if (count($ratings) > 50) {
        fail('Please answer all evaluation questions.');
    }

    $clean = [];
    foreach (array_values($ratings) as $value) {
        if (!is_numeric($value)) {
            fail('Please answer all evaluation questions.');
        }
        $rating = (int) $value;
        if ($rating < 1 || $rating > 5) {
            fail('Please answer all evaluation questions.');
        }
        $clean[] = $rating;
    }

    return $clean;
}

/**
 * Store one SERVE evaluation.
 *
 * @param mixed $payload {studentType, email, fullName, staff, ratings, strengths, improve}
 * @return array{success:bool,reference:string}
 */
function act_submitEvaluation($payload = null)
{
    $payload = is_array($payload) ? $payload : [];

    $studentType = limit_text($payload['studentType'] ?? '', 40);
    $email = normalize_email($payload['email'] ?? '');
    $fullName = limit_text($payload['fullName'] ?? '', 160);
    $staffName = limit_text($payload['staff'] ?? '', 160);
    $strengths = limit_text($payload['strengths'] ?? '', 2000);
    $improve = limit_text($payload['improve'] ?? '', 2000);

    if (!in_array($studentType, ['Alumni Student', 'Present Student'], true)) {
        fail('Please select your student type.');
    }
    if (!is_valid_email($email)) {
        fail('Please enter a valid email address.');
    }

    $domain = preg_quote((string) config('university_email_domain', 'panpacificu.edu.ph'), '/');
    if ($studentType === 'Present Student' && !preg_match('/@' . $domain . '$/i', $email)) {
        fail('Present Students must use their university email ending in @panpacificu.edu.ph.');
    }

    if ($staffName === '') {
        fail('Please select the staff member you are evaluating.');
    }

    $staff = db_one('SELECT StaffID, Name FROM staff WHERE Active <> 0 AND Name = ? LIMIT 1', [$staffName]);
    if ($staff === null) {
        fail('The selected staff member is no longer in the active staff directory. Please refresh the page and select again.');
    }

    $ratings = normalise_ratings($payload['ratings'] ?? null);
    $questionCount = count($ratings);
    $average = round(array_sum($ratings) / $questionCount, 2);

    $now = app_now();
    $reference = create_evaluation_reference();

    // Extremely unlikely, but never store a duplicate reference.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $exists = db_value('SELECT EvaluationID FROM evaluations WHERE Reference = ? LIMIT 1', [$reference]);
        if ($exists === null) {
            break;
        }
        $reference = create_evaluation_reference();
    }

    db_exec(
        'INSERT INTO evaluations (EvaluationID, Reference, StudentType, Email, FullName, StaffName, QuestionCount,
                                  AverageRating, RatingsJson, Strengths, Improve, CreatedAt, IpAddress)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            short_id('EVAL-'),
            $reference,
            $studentType,
            $email,
            $fullName,
            (string) $staff['Name'],
            $questionCount,
            $average,
            json_encode($ratings),
            $strengths,
            $improve,
            $now->format('Y-m-d H:i:s'),
            limit_text(request_ip(), 45),
        ]
    );

    audit_log('', $email, 'EVALUATION_SUBMITTED', '', $reference, 'Evaluated: ' . $staff['Name']);

    $greeting = $fullName !== '' ? 'Dear ' . $fullName . ',' : 'Dear Student,';
    $body = $greeting . "\r\n\r\n"
        . 'Thank you for evaluating ' . $staff['Name'] . ' of the ' . config('office_name') . ".\r\n\r\n"
        . 'Reference: ' . $reference . "\r\n"
        . 'Average rating: ' . number_format($average, 2) . ' / 5' . "\r\n\r\n"
        . 'Your feedback will be used strictly for quality assurance and staff development.' . "\r\n\r\n"
        . config('university_name') . "\r\n" . config('office_name');

    $subject = config('university_name') . ' Registrar ' . "\xE2\x80\xA2" . ' SERVE Evaluation ' . $reference;
    $mailResult = mail_send($email, $subject, $body);
    notification_log('', $email, 'EVALUATION_CONFIRMATION', $subject, $body, $mailResult['status']);

    return [
        'success'   => true,
        'reference' => $reference,
    ];
}

/**
 * Aggregated results for reporting (not used by the form, handy for the
 * registrar: action "getEvaluationSummary").
 *
 * @return array{total:int,average:float,perStaff:array<int,array<string,mixed>>}
 */
function act_getEvaluationSummary()
{
    $perStaff = [];
    $rows = db_all(
        'SELECT StaffName, COUNT(*) AS Responses, AVG(AverageRating) AS Rating
           FROM evaluations
          GROUP BY StaffName
          ORDER BY StaffName'
    );

    $total = 0;
    $weighted = 0.0;
    foreach ($rows as $row) {
        $responses = (int) $row['Responses'];
        $rating = round((float) $row['Rating'], 2);
        $total += $responses;
        $weighted += $rating * $responses;
        $perStaff[] = [
            'staff'     => (string) $row['StaffName'],
            'responses' => $responses,
            'average'   => $rating,
        ];
    }

    return [
        'total'    => $total,
        'average'  => $total > 0 ? round($weighted / $total, 2) : 0.0,
        'perStaff' => $perStaff,
    ];
}
