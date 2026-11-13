<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class QuotationDraft extends Model
{
    protected $table = 'quotation_drafts';

    private static bool $tableChecked = false;

    private static bool $assetsChecked = false;

    protected $fillable = [
        'user_id',
        'title',
        'quotation_type',
        'project_name',
        'recipient_company',
        'recipient_person',
        'items_count',
        'grand_total',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'items_count' => 'integer',
            'grand_total' => 'integer',
            'payload' => 'array',
        ];
    }

    public static function ensureTableExists(): void
    {
        self::syncPublishedBuildAssets();

        if (self::$tableChecked && ! app()->runningUnitTests()) {
            return;
        }

        $markerFile = storage_path('framework/cache/quotation_drafts_schema_v2.flag');

        if (! app()->runningUnitTests() && is_file($markerFile)) {
            self::$tableChecked = true;

            return;
        }

        try {
            if (Schema::hasTable('quotation_drafts')) {
                self::$tableChecked = true;
                try {
                    $driver = Schema::getConnection()->getDriverName();
                    if (in_array($driver, ['mysql', 'mariadb'], true)) {
                        DB::statement('ALTER TABLE `quotation_drafts` MODIFY `payload` LONGTEXT NOT NULL');
                    }
                } catch (Throwable) {
                    // Ignore column alter if not needed
                }

                if (! app()->runningUnitTests()) {
                    @file_put_contents($markerFile, '1');
                }

                return;
            }

            Schema::create('quotation_drafts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('title');
                $table->string('quotation_type', 32)->default('ariston');
                $table->string('project_name')->nullable();
                $table->string('recipient_company')->nullable();
                $table->string('recipient_person')->nullable();
                $table->unsignedInteger('items_count')->default(1);
                $table->unsignedBigInteger('grand_total')->default(0);
                $table->longText('payload');
                $table->timestamps();
            });

            self::$tableChecked = true;

            if (! app()->runningUnitTests()) {
                @file_put_contents($markerFile, '1');
            }
        } catch (Throwable) {
            // Ignore schema creation errors in read-only contexts
        }
    }

    /**
     * Ensure compiled Blade views stay in sync on shared hosting when manifest changes.
     */
    private static function syncPublishedBuildAssets(): void
    {
        if (self::$assetsChecked) {
            return;
        }

        self::$assetsChecked = true;

        try {
            $pubManifest = base_path('public/build/manifest.json');
            if (! is_file($pubManifest)) {
                return;
            }

            $pubMtime = (int) @filemtime($pubManifest);
            $viewsDir = storage_path('framework/views');
            if (! is_dir($viewsDir)) {
                return;
            }

            $mtimeMarker = $viewsDir.'/.manifest_mtime';
            if (is_file($mtimeMarker) && (int) @file_get_contents($mtimeMarker) === $pubMtime) {
                return;
            }

            foreach (glob($viewsDir.'/*.php') ?: [] as $compiledView) {
                if ((int) @filemtime($compiledView) < $pubMtime) {
                    @unlink($compiledView);
                }
            }

            @file_put_contents($mtimeMarker, (string) $pubMtime);
        } catch (Throwable) {
            // Ignore filesystem sync errors
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
