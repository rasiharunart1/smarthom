<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Widget extends Model
{
    protected $fillable = [
        'device_id',
        'widgets_data',
        'grid_config',
        'widget_count',
        'layout_version'
    ];

    protected $casts = [
        'widgets_data' => 'array',
        'grid_config' => 'array',
        'widget_count' => 'integer',
        'layout_version' => 'integer'
    ];

    /**
     * Get the device that owns this widget configuration
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get all widgets with their current values
     */
    public function getAllWidgets(): array
    {
        return $this->widgets_data ?? [];
    }

    /**
     * Get a specific widget by key
     */
    public function getWidget(string $key): ?array
    {
        return $this->widgets_data[$key] ?? null;
    }

    /**
     * Add a new widget
     */
    public function addWidget(string $key, array $widgetData): self
    {
        $widgets = $this->widgets_data ?? [];

        // Set default values
        $widgetData = array_merge([
            'name' => 'New Widget',
            'type' => 'text',
            'value' => '',
            'min' => 0,
            'max' => 100,
            'position_x' => 0,
            'position_y' => 0,
            'width' => 4,
            'height' => 2,
            'order' => count($widgets),
            'config' => [],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ], $widgetData);

        $widgets[$key] = $widgetData;

        $this->widgets_data = $widgets;
        $this->widget_count = count($widgets);
        $this->layout_version++;
        $this->save();

        $this->clearCache();

        return $this;
    }
    /**
     * Add multiple widgets at once
     */
    public function addWidgets(array $widgetsDataMap): self
    {
        $widgets = $this->widgets_data ?? [];

        foreach ($widgetsDataMap as $key => $widgetData) {
            // Set default values for each widget
            $widgetData = array_merge([
                'name' => 'New Widget',
                'type' => 'text',
                'value' => '',
                'min' => 0,
                'max' => 100,
                'position_x' => 0,
                'position_y' => 0,
                'width' => 4,
                'height' => 2,
                'order' => count($widgets),
                'config' => [],
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ], $widgetData);

            $widgets[$key] = $widgetData;
        }

        $this->widgets_data = $widgets;
        $this->widget_count = count($widgets);
        $this->layout_version++;
        $this->save();

        $this->clearCache();

        return $this;
    }

    /**
     * Update a widget
     */
    public function updateWidget(string $key, array $updates): self
    {
        $widgets = $this->widgets_data ?? [];

        if (!isset($widgets[$key])) {
            throw new \Exception("Widget key '{$key}' not found");
        }

        // Deep merge config: incoming values always win (including false/null/0).
        // array_merge alone is insufficient because it skips false booleans being
        // "overridden" when the key already exists with a truthy value in some PHP
        // quirks. Using explicit key assignment ensures alert_enabled=false sticks.
        if (isset($updates['config']) && isset($widgets[$key]['config']) && is_array($updates['config']) && is_array($widgets[$key]['config'])) {
            $merged = $widgets[$key]['config'];
            foreach ($updates['config'] as $cfgKey => $cfgVal) {
                $merged[$cfgKey] = $cfgVal; // Explicit override — respects false, null, 0
            }
            $updates['config'] = $merged;
        }

        $widgets[$key] = array_merge($widgets[$key], $updates);
        $widgets[$key]['updated_at'] = now()->toISOString();

        $this->widgets_data = $widgets;

        // Only increment version if layout changed (not just value)
        $layoutFields = ['position_x', 'position_y', 'width', 'height', 'name', 'type', 'config'];
        $layoutChanged = !empty(array_intersect(array_keys($updates), $layoutFields));

        if ($layoutChanged) {
            $this->layout_version++;
        }

        $this->save();
        $this->clearCache();

        return $this;
    }

    /**
     * Update only widget value (optimized for frequent updates)
     */
    public function updateWidgetValue(string $key, $value): self
    {
        $widgets = $this->widgets_data ?? [];

        if (!isset($widgets[$key])) {
            throw new \Exception("Widget key '{$key}' not found");
        }

        $widgets[$key]['value'] = (string)$value;
        $widgets[$key]['updated_at'] = now()->toISOString();

        $this->widgets_data = $widgets;
        $this->save();

        // Only clear value cache, not layout cache
        Cache::forget("widget_value:{$this->device_id}:{$key}");

        return $this;
    }

    /**
     * Remove a widget
     */
    public function removeWidget(string $key): self
    {
        $widgets = $this->widgets_data ?? [];

        if (isset($widgets[$key])) {
            unset($widgets[$key]);

            $this->widgets_data = $widgets;
            $this->widget_count = count($widgets);
            $this->layout_version++;
            $this->save();

            $this->clearCache();
        }

        return $this;
    }

    /**
     * Get widgets ordered by their order field
     */
    public function getOrderedWidgets(): array
    {
        $widgets = $this->widgets_data ?? [];

        uasort($widgets, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });

        return $widgets;
    }

    /**
     * Update widget positions (for drag & drop)
     */
    public function updatePositions(array $positions): self
    {
        $widgets = $this->widgets_data ?? [];

        foreach ($positions as $position) {
            $key = $position['key'] ?? null;
            if ($key && isset($widgets[$key])) {
                $widgets[$key]['position_x'] = $position['x'] ?? $widgets[$key]['position_x'];
                $widgets[$key]['position_y'] = $position['y'] ?? $widgets[$key]['position_y'];
                $widgets[$key]['width'] = $position['w'] ?? $widgets[$key]['width'];
                $widgets[$key]['height'] = $position['h'] ?? $widgets[$key]['height'];
                $widgets[$key]['updated_at'] = now()->toISOString();
            }
        }

        $this->widgets_data = $widgets;
        $this->layout_version++;
        $this->save();

        $this->clearCache();

        return $this;
    }

    /**
     * Get widget keys for MQTT mapping
     */
    public function getWidgetKeys(): array
    {
        return array_keys($this->widgets_data ?? []);
    }

    /**
     * Find widget key by name
     */
    public function findWidgetKeyByName(string $name): ?string
    {
        foreach ($this->widgets_data ?? [] as $key => $widget) {
            if (strcasecmp($widget['name'], $name) === 0) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Export widgets data for backup
     */
    public function exportData(): array
    {
        return [
            'device_id' => $this->device_id,
            'widgets_data' => $this->widgets_data,
            'grid_config' => $this->grid_config,
            'widget_count' => $this->widget_count,
            'layout_version' => $this->layout_version,
            'exported_at' => now()->toISOString()
        ];
    }

    /**
     * Import widgets data from backup
     */
    public function importData(array $data): self
    {
        $this->widgets_data = $data['widgets_data'] ?? [];
        $this->grid_config = $data['grid_config'] ?? $this->grid_config;
        $this->widget_count = $data['widget_count'] ?? count($this->widgets_data);
        $this->layout_version++;
        $this->save();

        $this->clearCache();

        return $this;
    }

    /**
     * Clear cache for this device's widgets
     */
    private function clearCache(): void
    {
        Cache::forget("device_widgets:{$this->device_id}");
        Cache::forget("device_layout:{$this->device_id}");
    }

    /**
     * Generate sequential widget key (e.g. toggle1, toggle2)
     */
    public static function generateSequentialKey(string $type, array $existingWidgets): string
    {
        $prefix = strtolower($type);
        $maxIndex = 0;

        foreach (array_keys($existingWidgets) as $key) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $key, $matches)) {
                $index = (int)$matches[1];
                if ($index > $maxIndex) {
                    $maxIndex = $index;
                }
            }
        }

        return $prefix . ($maxIndex + 1);
    }

    /**
     * Rename a widget key
     */
    public function renameWidget(string $oldKey, string $newKey): self
    {
        $widgets = $this->widgets_data ?? [];

        if (!isset($widgets[$oldKey])) {
            throw new \Exception("Widget key '{$oldKey}' not found");
        }

        if (isset($widgets[$newKey])) {
            throw new \Exception("Widget key '{$newKey}' already exists");
        }

        // Move data to new key
        $widgets[$newKey] = $widgets[$oldKey];
        unset($widgets[$oldKey]);

        // Update key reference in widget data if we tracked it there (we don't appear to, but good to be safe)
        // $widgets[$newKey]['key'] = $newKey; // The key is the array key only

        $this->widgets_data = $widgets;
        $this->save();
        $this->clearCache();

        return $this;
    }

    /**
     * Generate unique widget key (Legacy/Fallback)
     */
    public static function generateWidgetKey(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
        return $slug . '_' . substr(md5(uniqid()), 0, 6);
    }
}
