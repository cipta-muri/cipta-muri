@php
    $formWidgets = method_exists($this, 'getFormWidgets') ? $this->getFormWidgets() : [];
@endphp

@if (! empty($formWidgets))
    <x-filament-widgets::widgets
        :columns="method_exists($this, 'getFormWidgetsColumns') ? $this->getFormWidgetsColumns() : 2"
        :data="$this->getWidgetData()"
        :widgets="$formWidgets"
        class="fi-page-form-widgets"
    />
@endif
