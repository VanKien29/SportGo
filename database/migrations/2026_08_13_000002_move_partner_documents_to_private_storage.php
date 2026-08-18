<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_application_documents')) {
            return;
        }

        DB::table('partner_application_documents')
            ->select(['id', 'partner_application_id', 'file_path', 'media_id'])
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->each(function (object $document): void {
                $source = ltrim((string) $document->file_path, '/');
                if ($source === '' || ! Storage::disk('public')->exists($source)) {
                    return;
                }

                $target = 'partner-applications-private/'
                    . (int) $document->partner_application_id
                    . '/' . (int) $document->id . '-' . basename($source);

                if (! Storage::disk('local')->exists($target)) {
                    Storage::disk('local')->put($target, Storage::disk('public')->get($source));
                }

                DB::table('partner_application_documents')
                    ->where('id', $document->id)
                    ->update(['file_path' => $target]);

                if ($document->media_id) {
                    DB::table('media')
                        ->where('id', $document->media_id)
                        ->update(['file_path' => $target]);
                }

                Storage::disk('public')->delete($source);
            });
    }

    public function down(): void
    {
        // Private copies are intentionally retained. Re-exposing contract
        // evidence on the public disk during rollback would reduce security.
    }
};
