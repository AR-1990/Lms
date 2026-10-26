<?php

namespace App\Services;

use App\Models\AdmissionApplication;
use App\Models\Attachment;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\JobApplication;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentService
{
    /**
     * Records that can own attachments, and the permission needed to manage their files.
     * New modules (students, employees, assets…) register themselves here.
     *
     * @var array<string, array{model: class-string<Model>, permission: string}>
     */
    public const OWNERS = [
        'users' => ['model' => User::class, 'permission' => 'manage-users'],
        'students' => ['model' => Student::class, 'permission' => 'manage-students'],
        'admission_applications' => ['model' => AdmissionApplication::class, 'permission' => 'manage-admissions'],
        'employees' => ['model' => Employee::class, 'permission' => 'manage-hr'],
        'expenses' => ['model' => Expense::class, 'permission' => 'manage-expenses'],
        'job_applications' => ['model' => JobApplication::class, 'permission' => 'manage-recruitment'],
        'payment_proofs' => ['model' => PaymentProof::class, 'permission' => 'manage-fees'],
    ];

    private const DISK = 'local';

    public function ownerKeys(): array
    {
        return array_keys(self::OWNERS);
    }

    /**
     * @throws ModelNotFoundException
     */
    public function resolveOwner(string $owner, int $ownerId): Model
    {
        return self::OWNERS[$owner]['model']::query()->findOrFail($ownerId);
    }

    public function canManage(?User $actor, string $owner): bool
    {
        return $actor !== null
            && isset(self::OWNERS[$owner])
            && $actor->hasPermission(self::OWNERS[$owner]['permission']);
    }

    /**
     * Owner key (e.g. `users`) of an existing attachment.
     */
    public function ownerKeyOf(Attachment $attachment): ?string
    {
        foreach (self::OWNERS as $key => $definition) {
            if ($definition['model'] === $attachment->attachable_type) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Attachment>
     */
    public function forOwner(Model $owner): Collection
    {
        return Attachment::query()
            ->with('documentType:id,name')
            ->where('attachable_type', $owner::class)
            ->where('attachable_id', $owner->getKey())
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * Store the file on the private disk and record it. The file is removed again if saving fails.
     *
     * @param  array<string, mixed>  $meta
     */
    public function store(Model $owner, string $ownerKey, UploadedFile $file, array $meta): Attachment
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs("attachments/{$ownerKey}/{$owner->getKey()}", Str::uuid().'.'.$extension, self::DISK);

        try {
            return DB::transaction(fn (): Attachment => Attachment::create([
                ...$meta,
                'attachable_type' => $owner::class,
                'attachable_id' => $owner->getKey(),
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'is_verified' => false,
                'created_by' => request()->user()?->getKey(),
                'updated_by' => request()->user()?->getKey(),
            ]))->load('documentType:id,name');
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);

            throw $exception;
        }
    }

    public function setVerified(Attachment $attachment, bool $verified): Attachment
    {
        $attachment->update([
            'is_verified' => $verified,
            'verified_by' => $verified ? request()->user()?->getKey() : null,
            'verified_at' => $verified ? now() : null,
            'updated_by' => request()->user()?->getKey(),
        ]);

        return $attachment->load('documentType:id,name');
    }

    /**
     * Soft delete: the record leaves every list but the file stays for the audit trail.
     */
    public function delete(Attachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());
    }

    /**
     * Short-lived signed link that works in a browser tab or the mobile app without a token.
     */
    public function temporaryUrl(Attachment $attachment, int $minutes = 10): string
    {
        return URL::temporarySignedRoute('attachments.download', now()->addMinutes($minutes), ['attachment' => $attachment->getKey()]);
    }

    /**
     * Images and PDFs open in the browser; other files download with their original name.
     */
    public function response(Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);
        $inline = str_starts_with($attachment->mime_type, 'image/') || $attachment->mime_type === 'application/pdf';

        return $inline
            ? $disk->response($attachment->path, $attachment->original_name)
            : $disk->download($attachment->path, $attachment->original_name);
    }
}
