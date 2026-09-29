<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Models\ScopusAuthorId;
use App\Models\Teacher;
use App\Services\Scopus\RecordResolver;
use App\Services\Scopus\ScopusWorkbookImporter;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ScopusWorkbookImporterTest extends TestCase
{
    public function test_it_imports_approved_publications_from_workbook(): void
    {
        $spreadsheet = new Spreadsheet();
        
        $notHereSheet = $spreadsheet->getActiveSheet();
        $notHereSheet->setTitle('Not in our system');

        $headers = [
            'Scopus Title', 'Year', 'DOI', 'EID', 'Source Title', 'Document Type', 'Cited by',
            'Scopus — all authors', 'Daffodil authors on this paper',
            'Matched on', 'Our Publication ID', 'Our Title', 'Our Year', 'Our record came from',
            'Our authors', 'Authorship', 'What differs', 'Scopus 1st author', 'Our 1st author',
            'Decision', 'Notes',
        ];

        $notHereSheet->fromArray($headers, null, 'A1');

        $notHereSheet->fromArray([
            'Test New Scopus Paper', '2026', '10.1234/test.doi', '2-s2.0-9999', 'Journal of Testing', 'Article', '5',
            'Rahman, M. (111)', 'Rahman, M.',
            'Not found', '', '', '', '', '', '', '', '', '',
            'Approve', 'Looks good to import',
        ], null, 'A2');

        $notHereSheet->fromArray([
            'Unapproved Paper', '2026', '10.1234/unapproved', '2-s2.0-8888', 'Journal of Skip', 'Article', '0',
            'Unknown, A.', '',
            'Not found', '', '', '', '', '', '', '', '', '',
            '', 'No decision made',
        ], null, 'A3');

        $path = tempnam(sys_get_temp_dir(), 'scopus_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $importer = new ScopusWorkbookImporter(app(RecordResolver::class));
        $result = $importer->import($path);

        $this->assertSame(1, $result['publications_created']);
        $this->assertSame(1, $result['publications_skipped']);

        $publication = Publication::where('doi', '10.1234/test.doi')->first();
        $this->assertNotNull($publication);
        $this->assertSame('Test New Scopus Paper', $publication->title);
        $this->assertSame('approved', $publication->status);

        $this->assertNull(Publication::where('doi', '10.1234/unapproved')->first());

        @unlink($path);
    }

    public function test_it_binds_scopus_author_id_to_teacher(): void
    {
        $teacher = Teacher::first();

        if (! $teacher) {
            $this->markTestSkipped('No teacher in database.');
        }

        $spreadsheet = new Spreadsheet();
        $peopleSheet = $spreadsheet->getActiveSheet();
        $peopleSheet->setTitle('People');

        $headers = [
            'Scopus Name', 'Scopus Author ID', 'Email', 'Papers', 'Unit as Scopus wrote it',
            'Suggested Faculty', 'Suggested Department', 'How the unit was found',
            'Our guess', 'Teacher ID', 'Author ID', 'Our Name', 'Our Department',
            'Matched by', 'Confidence', 'Candidates', 'Who it might be', 'Decision', 'Notes',
        ];

        $peopleSheet->fromArray($headers, null, 'A1');

        $peopleSheet->fromArray([
            'Test Author', '777888999', 'test@diu.edu.bd', '3', 'Department of CSE',
            'FSIT', 'CSE', 'Unit match',
            'Our teacher', $teacher->id, '', $teacher->full_name, 'CSE',
            'name', 'likely', '', '',
            'Approve', 'Confirmed teacher',
        ], null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'scopus_people_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $importer = new ScopusWorkbookImporter(app(RecordResolver::class));
        $result = $importer->import($path);

        $this->assertSame(1, $result['people_linked']);

        $bound = ScopusAuthorId::where('scopus_author_id', '777888999')
            ->where('authorable_type', Teacher::class)
            ->where('authorable_id', $teacher->id)
            ->exists();

        $this->assertTrue($bound);

        @unlink($path);
    }

    public function test_it_reorders_author_name_from_surname_first_to_given_names_first(): void
    {
        $this->assertSame(
            'Md Mahmud Murshid',
            ScopusWorkbookImporter::formatAuthorName('Murshid, Md Mahmud')
        );

        $this->assertSame(
            'Md Mahmud Murshid',
            ScopusWorkbookImporter::formatAuthorName('Murshid, Md Mahmud (59559538700)')
        );

        $this->assertSame(
            'Xiang Fu',
            ScopusWorkbookImporter::formatAuthorName('Fu, Xiang')
        );

        $this->assertSame(
            'Already Correct Name',
            ScopusWorkbookImporter::formatAuthorName('Already Correct Name')
        );
    }
}
