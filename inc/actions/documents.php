<?php
/**
 * actions/documents.php - document catalog and public configuration.
 *
 * Port of the Apps Script functions getPublicConfig() + getDocumentTypes().
 */

/**
 * Active document types, in catalog order.
 *
 * @return array<int,array{code:string,name:string,description:string,fee:float,days:int,requirements:array<int,string>}>
 */
function document_types()
{
    $rows = db_all(
        'SELECT DocumentCode, DocumentName, Description, BaseFee, TypicalDays, Requirements
           FROM document_types
          WHERE Active <> 0
          ORDER BY SortOrder, DocumentName'
    );

    $documents = [];
    foreach ($rows as $row) {
        $requirements = array_values(array_filter(
            array_map('trim', explode(';', (string) $row['Requirements'])),
            function ($value) {
                return $value !== '';
            }
        ));

        $documents[] = [
            'code'         => (string) $row['DocumentCode'],
            'name'         => (string) $row['DocumentName'],
            'description'  => (string) $row['Description'],
            'fee'          => (float) $row['BaseFee'],
            'days'         => (int) $row['TypicalDays'],
            'requirements' => $requirements,
        ];
    }

    return $documents;
}

/** One document type by code, or null. */
function document_by_code($code)
{
    foreach (document_types() as $document) {
        if ($document['code'] === (string) $code) {
            return $document;
        }
    }
    return null;
}

/** Apps Script: getPublicConfig() */
function act_getPublicConfig()
{
    return [
        'universityName' => (string) config('university_name'),
        'officeName'     => (string) config('office_name'),
        'blue'           => (string) config('brand_blue'),
        'green'          => (string) config('brand_green'),
        'documents'      => document_types(),
    ];
}

/** Apps Script: getDocumentTypes() */
function act_getDocumentTypes()
{
    return document_types();
}
