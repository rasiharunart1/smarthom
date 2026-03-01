<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Jalankan migrasi
     */
    public function up(): void
    {
        echo "🚀 Starting widgets table refactor...\n";

        // 1️⃣ Rename tabel lama dulu agar tidak bentrok
        $this->renameOldWidgetsTable();

        // 2️⃣ Buat tabel baru (struktur JSON)
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->unique()->constrained()->onDelete('cascade');

            // Semua widget disimpan dalam JSON
            $table->json('widgets_data')->comment('All widgets configuration and values');

            // Layout gridstack / UI
            $table->json('grid_config')->nullable()->comment('Grid layout configuration');

            // Metadata
            $table->integer('widget_count')->default(0);
            $table->integer('layout_version')->default(1);

            $table->timestamps();

            $table->index('device_id');
            $table->index('updated_at');
        });

        // 3️⃣ Migrasikan data dari backup lama ke struktur baru
        $this->migrateOldDataToNew();

        echo "✅ Migration completed successfully!\n";
    }

    /**
     * Rename tabel lama → widgets_backup
     */
    private function renameOldWidgetsTable(): void
    {
        $driver = DB::getDriverName();

        // Cek apakah tabel widgets lama ada
        if (!Schema::hasTable('widgets')) {
            echo "⚠️ No old 'widgets' table found. Skipping rename.\n";
            return;
        }

        echo "🔄 Renaming old widgets table to widgets_backup...\n";

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE widgets RENAME TO widgets_backup');
        } else {
            DB::statement('RENAME TABLE widgets TO widgets_backup');
        }

        echo "✅ Old widgets table renamed to widgets_backup.\n";
    }

    /**
     * Migrasi data lama ke tabel baru (format JSON)
     */
    private function migrateOldDataToNew(): void
    {
        echo "🔄 Migrating widget data to new structure...\n";

        // Pastikan backup tabel tersedia
        if (!Schema::hasTable('widgets_backup')) {
            echo "⚠️ widgets_backup table not found. Skipping migration.\n";
            return;
        }

        $devices = DB::table('devices')->get();
        $totalDevices = count($devices);
        $processed = 0;

        foreach ($devices as $device) {
            $processed++;
            echo "➡️ Processing device {$processed}/{$totalDevices} (ID: {$device->id})...\n";

            // Ambil semua widget lama untuk device ini
            $oldWidgets = DB::table('widgets_backup')
                ->where('device_id', $device->id)
                ->orderBy('id') // aman karena 'order' sudah tidak ada
                ->get();

            if ($oldWidgets->isEmpty()) {
                echo "  ℹ️ No widgets found for device {$device->id}\n";
                continue;
            }

            $widgetsData = [];

            foreach ($oldWidgets as $widget) {
                $key = $this->generateWidgetKey($widget->name ?? 'widget', $widget->id);

                $widgetsData[$key] = [
                    'name' => $widget->name ?? 'Unnamed',
                    'type' => $widget->type ?? 'unknown',
                    'value' => $widget->value ?? '',
                    'min' => $widget->min ?? 0,
                    'max' => $widget->max ?? 100,
                    'position_x' => $widget->position_x ?? 0,
                    'position_y' => $widget->position_y ?? 0,
                    'width' => $widget->width ?? 4,
                    'height' => $widget->height ?? 2,
                    'order' => $widget->order ?? 0,
                    'config' => json_decode($widget->config ?? '{}', true),
                    'created_at' => $widget->created_at ?? now(),
                    'updated_at' => $widget->updated_at ?? now(),
                ];
            }

            // Simpan ke tabel baru
            DB::table('widgets')->insert([
                'device_id' => $device->id,
                'widgets_data' => json_encode($widgetsData),
                'grid_config' => json_encode([
                    'columns' => 12,
                    'cellHeight' => 100,
                    'margin' => 20,
                ]),
                'widget_count' => count($widgetsData),
                'layout_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            echo "  ✅ Migrated " . count($widgetsData) . " widgets for device {$device->id}\n";
        }

        echo "🎯 All devices processed: {$totalDevices} total.\n";
    }

    /**
     * Buat nama unik untuk setiap widget
     */
    private function generateWidgetKey(string $name, int $id): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($name)));
        $slug = trim($slug, '_');
        if (empty($slug)) $slug = 'widget';
        return "{$slug}_{$id}";
    }

    /**
     * Rollback perubahan
     */
    public function down(): void
    {
        echo "⏪ Rolling back widget migration...\n";
        $driver = DB::getDriverName();

        try {
            Schema::dropIfExists('widgets');

            if (Schema::hasTable('widgets_backup')) {
                if ($driver === 'pgsql') {
                    DB::statement('ALTER TABLE widgets_backup RENAME TO widgets');
                } else {
                    DB::statement('RENAME TABLE widgets_backup TO widgets');
                }
                echo "✅ widgets_backup restored as widgets\n";
            } else {
                echo "⚠️ widgets_backup table not found. Manual restore may be required.\n";
            }

        } catch (\Exception $e) {
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
        }
    }
};
