@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ __('Widget Metadata') }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Manage widget keys and configurations for {{ $device->name }}
                </p>
            </div>
            <a href="{{ route('devices.show', $device) }}" 
               class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                &larr; Back to Device
            </a>
        </div>

        <!-- Glassmorphism Card -->
        <div class="bg-white/30 dark:bg-gray-800/30 backdrop-blur-lg border border-white/20 dark:border-gray-700/30 rounded-2xl p-6 shadow-xl relative overflow-hidden">
            <!-- Ambient Background Glow -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-pink-500/20 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 overflow-x-auto">
                @if(count($widgets) > 0)
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="p-4 font-medium">{{ __('Internal Key') }}</th>
                            <th class="p-4 font-medium">{{ __('MQTT Index') }} <span class="text-xs text-gray-400 font-normal">(typeIndex)</span></th>
                            <th class="p-4 font-medium">{{ __('Name') }} <span class="text-xs text-gray-400 font-normal">(Display)</span></th>
                            <th class="p-4 font-medium">{{ __('Type') }}</th>
                            <th class="p-4 font-medium">{{ __('MQTT Control Topic') }}</th>
                            <th class="p-4 font-medium">{{ __('Current Value') }}</th>
                            <th class="p-4 font-medium text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 dark:text-gray-300 divide-y divide-gray-200 dark:divide-gray-700/50">
                        @foreach($widgets as $key => $widget)
                        <tr class="hover:bg-white/10 dark:hover:bg-white/5 transition-colors group">
                            <td class="p-4">
                                <span class="font-mono text-xs text-gray-400 bg-gray-500/5 px-2 py-1 rounded">
                                    {{ $key }}
                                </span>
                            </td>
                            <td class="p-4">
                                <span class="font-mono text-sm text-indigo-600 dark:text-indigo-400 font-bold bg-indigo-500/10 px-2 py-1 rounded">
                                    {{ $widget['type_index'] ?? $key }}
                                </span>
                            </td>
                            <td class="p-4 font-medium">
                                {{ $widget['name'] }}
                            </td>
                            <td class="p-4">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    {{ match($widget['type']) {
                                        'toggle' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                        'slider' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                        'gauge' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
                                        'chart' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                                        default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    } }}">
                                    {{ ucfirst($widget['type']) }}
                                </span>
                            </td>
                            <td class="p-4">
                                <code class="text-xs text-gray-500 dark:text-gray-400 break-all bg-black/20 px-2 py-1 rounded select-all cursor-pointer" title="Click to copy" onclick="copyToClipboard('users/{{ $device->user_id }}/devices/{{ $device->device_code }}/control/{{ $widget['type_index'] ?? $key }}')">
                                    .../control/{{ $widget['type_index'] ?? $key }}
                                </code>
                            </td>
                            <td class="p-4 font-mono text-sm">
                                <span class="text-{{ $widget['value'] ? 'white' : 'gray-500' }}">
                                    {{ Illuminate\Support\Str::limit($widget['value'] ?? 'N/A', 15) }}
                                </span>
                            </td>
                            <td class="p-4 text-right">
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                    <!-- Edit Trigger -->
                                    <button onclick="openEditKeyModal('{{ $key }}', '{{ $widget['name'] }}', '{{ $widget['type_index'] ?? $key }}')" 
                                            class="text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 text-sm font-medium mr-2">
                                        Edit Key/Index
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="bg-gray-100 dark:bg-gray-700/50 p-4 rounded-full mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">No widgets found</h3>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Start by adding widgets to your device dashboard.</p>
                    <div class="mt-6">
                        <a href="{{ route('devices.show', $device) }}" 
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors shadow-lg shadow-indigo-500/30">
                            Go to Dashboard &rarr;
                        </a>
                    </div>
                </div>
                @endif
            </div>

            <!-- Bulk Edit Hint -->
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700/50 text-sm text-gray-500 dark:text-gray-400">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-indigo-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p>
                        <strong>Use these keys in your ESP32 code:</strong><br>
                        When publishing via MQTT, use the format <code>users/{{ $device->user_id }}/devices/{{ $device->device_code }}/control/{key}</code>.
                        <br>For example: <code>.../control/<span class="text-indigo-600 dark:text-indigo-400 font-mono">toggle1</span></code>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Simple JS for Modal (Placeholder for now until fully implemented if requested) -->
<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Topic copied: ' + text);
        }).catch(err => {
            console.error('Failed to copy: ', err);
        });
    }

    function openEditKeyModal(currentKey, name, currentIndex) {
        const newKey = prompt(`Rename internal key for "${name}":\n(Changing this breaks layout scripts if you use the key)`, currentKey);
        const newIndex = prompt(`Rename MQTT index for "${name}":\n(e.g. toggle1, toggle2)\nThis is what the hardware uses to publish.`, currentIndex);
        
        if ((newKey && newKey !== currentKey) || (newIndex && newIndex !== currentIndex)) {
            const body = {
                renames: {
                    [currentKey]: newKey || currentKey
                }
            };
            
            // Note: Our bulk endpoint doesn't support changing type_index yet.
            // I should update the WidgetController to handle any field updates or add a specific one.
            // For now, let's just use the update endpoint if available, or I'll implement a new logic.
            
            fetch("{{ route('widgets.update', [$device, 'WIDGET_KEY']) }}".replace('WIDGET_KEY', currentKey), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    ...(newKey && { key: newKey }),
                    ...(newIndex && { type_index: newIndex }),
                    name: name // keep current name
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.message || JSON.stringify(data.errors)));
                }
            })
            .catch(err => alert('Failed to update widget'));
        }
    }
</script>
@endsection
