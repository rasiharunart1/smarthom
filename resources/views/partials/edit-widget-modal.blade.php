<div class="modal fade" id="editWidgetModal" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="color: #60a5fa; font-weight: 600;">
                    <i class="fas fa-edit mr-2"></i>Tune Module Parameters
                </h5>
                <button type="button" class="close text-white opacity-1" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="editWidgetForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" id="editWidgetKey" name="widget_key">
                <div class="modal-body py-4">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Module Label</label>
                            <input type="text" class="form-control glass-input" id="editWidgetName" name="name" required
                                placeholder="e.g. Living Room Light">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Module Type</label>
                            <select class="form-control glass-input" id="editWidgetType" name="type" required>
                                <option value="toggle">Toggle Switch</option>
                                <option value="slider">Slider Control</option>
                                <option value="gauge">Gauge Meter</option>
                                <option value="text">Text Display</option>
                                <option value="chart">Real-time Chart</option>
                            </select>
                        </div>
                        <div class="col-12 mb-4 chart-source-field" style="display: none;">
                            <label class="glass-label">Chart Data Source (Variable)</label>
                            <select class="form-control glass-input" id="editWidgetSource" name="config[source_key]">
                                <option value="">-- Select Variable --</option>
                                <!-- Populated via JS -->
                            </select>
                            <small class="text-muted">Select the sensor variable to display on this chart.</small>
                        </div>
                        <div class="col-md-6 mb-4 chart-y-axis-step-field" style="display: none;">
                            <label class="glass-label">Y-Axis Step Size</label>
                            <input type="number" class="form-control glass-input" id="editWidgetYAxisStep" name="config[y_axis_step]" placeholder="e.g. 10" step="0.1">
                            <small class="text-muted">Interval between Y-axis tick marks.</small>
                        </div>
                        <div class="col-md-6 mb-4 edit-range-field" style="display: none;">
                            <label class="glass-label">Minimum Scale</label>
                            <input type="number" class="form-control glass-input" id="editWidgetMin" name="min">
                        </div>
                        <div class="col-md-6 mb-4 edit-range-field" style="display: none;">
                            <label class="glass-label">Maximum Scale</label>
                            <input type="number" class="form-control glass-input" id="editWidgetMax" name="max">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Horizontal Span</label>
                            <select class="form-control glass-input" id="editWidgetWidth" name="width">
                                <option value="3">Quarter (3/12)</option>
                                <option value="4">Standard (4/12)</option>
                                <option value="6">Half View (6/12)</option>
                                <option value="12">Full Width (12/12)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Vertical Stack</label>
                            <select class="form-control glass-input" id="editWidgetHeight" name="height">
                                <option value="1">Slim (1)</option>
                                <option value="2">Default (2)</option>
                                <option value="3">Expanded (3)</option>
                                <option value="4">Full Height (4)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4 text-field-group">
                            <label class="glass-label">Telemetry Unit</label>
                            <input type="text" class="form-control glass-input" id="editWidgetUnit" name="config[unit]" placeholder="e.g. °C, %, kW">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="glass-label">Visual Icon</label>
                            <select class="form-control glass-input" id="editWidgetIcon" name="config[icon]">
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
                        <div class="col-12 mt-4">
                            <hr class="border-secondary opacity-25 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-white mb-0">
                                    <i class="fas fa-calendar-alt mr-2 text-info"></i>Automations & Scheduling
                                </h6>
                                <button type="button" class="btn btn-sm glass-button" onclick="addScheduleRow()" 
                                    style="background: rgba(0, 255, 255, 0.1); border-color: rgba(0, 255, 255, 0.2); color: #22d3ee;">
                                    <i class="fas fa-plus mr-1"></i> Add Rule
                                </button>
                            </div>
                            
                            <div id="scheduleContainer" class="d-flex flex-column gap-3">
                                <!-- Dynamic rows -->
                            </div>
                            
                            <div id="noScheduleMsg" class="text-center py-3 rounded" style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1);">
                                <small class="text-muted">No automation rules defined for this module.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 justify-content-center">
                    <button type="button" class="btn glass-button btn-secondary mr-2" data-dismiss="modal">
                        Discard
                    </button>
                    <button type="submit" class="btn glass-button btn-primary" id="submitEditWidgetBtn" style="background: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.3); color: #60a5fa;">
                        Sync Parameters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    #editWidgetForm select option {
        background: #0a0e27;
        color: white;
    }
</style>
