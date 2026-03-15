<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .settings-container { max-width: 800px; margin: 2rem auto; }
        .settings-header { background: white; border-radius: 1rem 1rem 0 0; border-bottom: 1px solid #dee2e6; padding: 1.5rem; }
        .settings-content { background: white; border-radius: 0 0 1rem 1rem; padding: 2rem; }
        .settings-group { margin-bottom: 1rem; border: 1px solid #e9ecef; border-radius: 0.75rem; overflow: hidden; }
        .settings-group-header { background-color: #f8f9fa; padding: 1rem 1.5rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .settings-group-title { font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
        .settings-group-body { padding: 1.5rem; display: none; }
        .setting-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 1rem 0; border-bottom: 1px solid #f1f3f5; }
        .setting-item:last-child { border-bottom: none; }
        .setting-info { flex: 1; padding-right: 2rem; }
        .setting-label { font-weight: 500; display: flex; align-items: center; gap: 0.5rem; }
        .setting-description { font-size: 0.875rem; color: #6c757d; }
        .setting-control { width: 300px; }
        .form-select, .form-control { border-radius: 0.5rem; border: 1px solid #dee2e6; padding: 0.5rem 1rem; }
        .btn-outline-secondary, .btn-outline-primary { border-radius: 0 0.5rem 0.5rem 0; }
        .badge-custom { background-color: #cff4fc; color: #055160; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 1rem; }
        .chevron { transition: transform 0.3s; }
        .chevron.rotated { transform: rotate(180deg); }
    </style>
</head>
<body>
    <div class="container settings-container">
        <div class="settings-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Paramètres</h1>
                <span class="badge bg-info text-dark">Paramètres globaux</span>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success mt-4">{{ session('success') }}</div>
        @endif

        <div class="settings-content">
            @forelse($groups as $groupKey => $group)
                <div class="settings-group">
                    <div class="settings-group-header" onclick="toggleGroup('{{ $groupKey }}')">
                        <div class="settings-group-title">
                            <span>{{ $group['icon'] ?? '⚙️' }}</span>
                            {{ $group['label'] ?? $groupKey }}
                        </div>
                        <span class="chevron" id="chevron-{{ $groupKey }}">▼</span>
                    </div>

                    <div class="settings-group-body" id="group-{{ $groupKey }}">
                        @foreach($group['fields'] as $fieldKey)
                            @php
                                $value = $settings[$fieldKey] ?? null;
                                $fieldConfig = $fieldsConfig[$fieldKey] ?? [];
                                $fieldType = $fieldConfig['type'] ?? 'text';
                                $label = $fieldConfig['label'] ?? ucfirst(str_replace(['.', '_'], ' ', $fieldKey));
                            @endphp

                            <div class="setting-item">
                                <div class="setting-info">
                                    <div class="setting-label">{{ $label }}</div>
                                    @if(!empty($fieldConfig['description']))
                                        <div class="setting-description">{{ $fieldConfig['description'] }}</div>
                                    @endif
                                </div>

                                <div class="setting-control">
                                    <form action="{{ route('settings.update') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="key" value="{{ $fieldKey }}">

                                        @if($fieldType === 'boolean')
                                            <div class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" name="value" value="1"
                                                    {{ $value ? 'checked' : '' }}
                                                    onchange="this.form.submit()">
                                                <input type="hidden" name="checkbox" value="0">
                                            </div>
                                        @elseif($fieldType === 'select')
                                            <div class="input-group">
                                                <select name="value" class="form-select">
                                                    @foreach($fieldConfig['options'] ?? [] as $optionValue => $optionLabel)
                                                        <option value="{{ $optionValue }}" {{ $value == $optionValue ? 'selected' : '' }}>
                                                            {{ $optionLabel }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-outline-primary" type="submit">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </div>
                                        @else
                                            <div class="input-group">
                                                <input type="{{ $fieldType === 'number' ? 'number' : 'text' }}" 
                                                       name="value" value="{{ $value }}" class="form-control"
                                                       placeholder="{{ $fieldConfig['placeholder'] ?? '' }}"
                                                       @if($fieldType === 'number' && isset($fieldConfig['min'])) min="{{ $fieldConfig['min'] }}" @endif
                                                       @if($fieldType === 'number' && isset($fieldConfig['max'])) max="{{ $fieldConfig['max'] }}" @endif>
                                                <button class="btn btn-outline-secondary" type="submit">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </div>
                                        @endif
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-muted text-center py-5">Aucun groupe de paramètres trouvé.</p>
            @endforelse
        </div>
    </div>

    <script>
        function toggleGroup(groupKey) {
            const group = document.getElementById('group-' + groupKey);
            const chevron = document.getElementById('chevron-' + groupKey);
            if (group.style.display === 'block') {
                group.style.display = 'none';
                chevron.classList.remove('rotated');
            } else {
                group.style.display = 'block';
                chevron.classList.add('rotated');
            }
        }
    </script>
</body>
</html>