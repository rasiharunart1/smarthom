<div class="modal fade" id="addWidgetModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="color: var(--primary-green-light); font-weight: 600;">
                    <i class="fas fa-plus-circle mr-2"></i>Add New Widget
                </h5>
                <button type="button" class="close text-white opacity-1" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="addWidgetForm" action="{{ route('widgets.store', $device) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body py-4">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <label class="glass-label">Modules Quantity</label>
                            <div class="d-flex align-items-center">
                                <input type="number" class="form-control glass-input" name="quantity" id="widgetQuantity" value="1" min="1" max="50" style="width: 100px;">
                                <small class="ml-3 text-muted" id="bulkNote" style="display: none;">
                                    <i class="fas fa-info-circle mr-1"></i> Names will be generated automatically: (e.g. Toggle 1, Toggle 2...)
                                </small>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4" id="nameFieldGroup">
                            <label class="glass-label">Widget Name</label>
                            <input type="text" class="form-control glass-input" name="name" id="widgetName"
                                placeholder="e.g. Living Room Light">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Widget Type</label>
                            <select class="form-control glass-input" id="widgetType" name="type" required>
                                <option value="">Select Type</option>
                                <option value="toggle">Toggle Switch</option>
                                <option value="slider">Slider Control</option>
                                <option value="gauge">Gauge Meter</option>
                                <option value="text">Text Display</option>
                                <option value="chart">Real-time Chart</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4 range-field" style="display: none;">
                            <label class="glass-label">Minimum Value</label>
                            <input type="number" class="form-control glass-input" name="min" value="0">
                        </div>
                        <div class="col-md-6 mb-4 range-field" style="display: none;">
                            <label class="glass-label">Maximum Value</label>
                            <input type="number" class="form-control glass-input" name="max" value="100">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Width (Column)</label>
                            <select class="form-control glass-input" name="width">
                                <option value="3">Small (3/12)</option>
                                <option value="4" selected>Medium (4/12)</option>
                                <option value="6">Large (6/12)</option>
                                <option value="12">Full Width (12/12)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Height (Grid)</label>
                            <select class="form-control glass-input" name="height">
                                <option value="1">Short (1)</option>
                                <option value="2" selected>Medium (2)</option>
                                <option value="3">Tall (3)</option>
                                <option value="4">Extra Tall (4)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Unit</label>
                            <input type="text" class="form-control glass-input" name="config[unit]" placeholder="e.g. °C, %, kW">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Display Icon</label>
                            <select class="form-control glass-input" name="config[icon]">
                                <option value="cube">Default</option>
                                <option value="lightbulb">Light Bulb</option>
                                <option value="thermometer-half">Temperature</option>
                                <option value="tint">Humidity</option>
                                <option value="fan">Fan</option>
                                <option value="plug">Socket</option>
                                <option value="bolt">Power</option>
                                <option value="fire">Heater</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="glass-label">Description</label>
                            <textarea class="form-control glass-input" name="config[description]" rows="2" 
                                placeholder="Brief description of this widget"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 justify-content-center">
                    <button type="button" class="btn glass-button btn-secondary mr-2" data-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn glass-button glass-button-primary" id="submitWidgetBtn">
                        Save Widget
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const qtyInput = document.getElementById('widgetQuantity');
        const nameField = document.getElementById('nameFieldGroup');
        const widgetNameInput = document.getElementById('widgetName');
        const bulkNote = document.getElementById('bulkNote');
        const form = document.getElementById('addWidgetForm');
        const bulkRoute = "{{ route('widgets.bulk-store', $device) }}";
        const singleRoute = "{{ route('widgets.store', $device) }}";

        qtyInput.addEventListener('input', function() {
            const qty = parseInt(this.value) || 1;
            if (qty > 1) {
                nameField.style.display = 'none';
                widgetNameInput.required = false;
                bulkNote.style.display = 'block';
                form.action = bulkRoute;
            } else {
                nameField.style.display = 'block';
                widgetNameInput.required = true;
                bulkNote.style.display = 'none';
                form.action = singleRoute;
            }
        });

        // Initialize on load
        qtyInput.dispatchEvent(new Event('input'));
    });
</script>

<style>
    .modal-backdrop {
        background: rgba(0, 0, 0, 0.8) !important;
    }
    
    #addWidgetForm select option {
        background: #0a0e27;
        color: white;
    }
</style>