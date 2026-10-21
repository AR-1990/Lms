<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Lookup;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    /**
     * Set up seeded users and document types, with a fake private disk.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@lms.test')->first();
        $this->student = User::where('email', 'student@lms.test')->first();
    }

    private function upload(?UploadedFile $file = null, array $extra = []): TestResponse
    {
        return $this->actingAs($this->admin, 'sanctum')->post('/api/erp/attachments', [
            'owner' => 'users',
            'owner_id' => $this->student->id,
            'file' => $file ?? UploadedFile::fake()->image('b-form.png'),
            ...$extra,
        ], ['Accept' => 'application/json']);
    }

    public function test_upload_stores_privately_and_returns_a_signed_link(): void
    {
        $cnic = Lookup::where('type', 'document_type')->where('name', 'CNIC')->first();

        $response = $this->upload(null, ['document_type_id' => $cnic->id, 'document_number' => '35202-1234567-1']);

        $response->assertCreated()
            ->assertJsonPath('message', 'File uploaded.')
            ->assertJsonPath('data.document_type.name', 'CNIC')
            ->assertJsonPath('data.is_image', true)
            ->assertJsonPath('data.is_verified', false);

        $attachment = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringStartsWith('attachments/users/'.$this->student->id.'/', $attachment->path);
        $this->assertStringContainsString('signature=', $response->json('data.url'));
        $this->assertDatabaseHas('activity_logs', ['module' => 'attachments', 'action' => 'created', 'subject_id' => $attachment->id]);
    }

    public function test_upload_rejects_wrong_type_large_files_and_unknown_document_types(): void
    {
        $this->upload(UploadedFile::fake()->create('virus.exe', 10))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.file.0', 'Upload an image (JPG, PNG, WEBP), PDF, Word, or Excel file.');

        $this->upload(UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.file.0', 'Files can be up to 10 MB.');

        $gender = Lookup::where('type', 'gender')->first();
        $this->upload(null, ['document_type_id' => $gender->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.fields.document_type_id.0', 'Choose a document type from the list.');

        $this->assertSame(0, Attachment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_user_without_permission_cannot_list_upload_or_delete(): void
    {
        $this->upload();
        $attachment = Attachment::first();
        $teacher = User::where('email', 'teacher@lms.test')->first();

        $this->actingAs($teacher, 'sanctum')->getJson('/api/erp/attachments?owner=users&owner_id='.$this->student->id)->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->deleteJson('/api/erp/attachments/'.$attachment->id)->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->post('/api/erp/attachments', [
            'owner' => 'users',
            'owner_id' => $this->student->id,
            'file' => UploadedFile::fake()->image('x.png'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertNotSoftDeleted($attachment);
    }

    public function test_index_lists_files_of_one_owner_and_rejects_unknown_owner(): void
    {
        $this->upload();
        $this->upload(UploadedFile::fake()->create('result.pdf', 20, 'application/pdf'));

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/attachments?owner=users&owner_id='.$this->student->id)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.original_name', 'result.pdf');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/attachments?owner=users&owner_id='.$this->admin->id)
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/erp/attachments?owner=planets&owner_id=1')
            ->assertUnprocessable();
    }

    public function test_signed_link_downloads_and_expires(): void
    {
        $this->upload();
        $attachment = Attachment::first();

        $this->get(URL::temporarySignedRoute('attachments.download', now()->addMinutes(10), ['attachment' => $attachment->id]))
            ->assertOk();

        $this->get(route('attachments.download', $attachment))->assertForbidden();

        $expired = URL::temporarySignedRoute('attachments.download', now()->addMinutes(10), ['attachment' => $attachment->id]);
        $this->travel(11)->minutes();
        $this->get($expired)->assertForbidden();
    }

    public function test_verify_toggles_and_delete_soft_deletes_but_keeps_the_file(): void
    {
        $this->upload();
        $attachment = Attachment::first();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/erp/attachments/'.$attachment->id.'/verify', ['is_verified' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Marked as verified.')
            ->assertJsonPath('data.is_verified', true);

        $this->assertSame($this->admin->id, $attachment->refresh()->verified_by);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/erp/attachments/'.$attachment->id)
            ->assertOk()
            ->assertJsonPath('message', 'File deleted.');

        $this->assertSoftDeleted($attachment);
        Storage::disk('local')->assertExists($attachment->path);
    }
}
