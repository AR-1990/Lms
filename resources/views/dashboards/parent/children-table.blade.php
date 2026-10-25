{{-- One row per child (parent dashboard and My Children). Params: children (ParentService rows). --}}
@if ($children === [])
    <p class="dash-muted">No children are linked to your account.</p>
@else
    <div class="dash-table-wrap">
        <table class="dash-table">
            <thead>
                <tr><th>Name</th><th>Class</th><th>Roll No</th><th>Attendance</th><th>Last Result</th><th>Bus</th><th>Fee Due</th><th class="dash-col-actions"><span class="dash-visually-hidden">Open</span></th></tr>
            </thead>
            <tbody>
                @foreach ($children as $child)
                    <tr>
                        <td><strong>{{ $child['name'] }}</strong><br><span class="dash-muted">{{ $child['admission_no'] }}</span></td>
                        <td>{{ trim(($child['class'] ?? '').' '.($child['section'] ?? '')) ?: '—' }}</td>
                        <td>{{ $child['roll_no'] ?? '—' }}</td>
                        <td>{{ $child['attendance_percentage'] === null ? '—' : $child['attendance_percentage'].'%' }}</td>
                        <td>{{ $child['latest_result'] ? $child['latest_result']['exam'].': '.$child['latest_result']['percentage'].'% ('.$child['latest_result']['grade'].')' : '—' }}</td>
                        <td>
                            @if ($child['bus'])
                                {{ $child['bus']['route'] }} · {{ $child['bus']['stop'] }}<br>
                                <span class="dash-muted">Pickup {{ $child['bus']['pickup_time'] ?? '—' }}{{ $child['bus']['driver'] ? ' · '.$child['bus']['driver'] : '' }}{{ $child['bus']['driver_phone'] ? ' '.$child['bus']['driver_phone'] : '' }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="dash-badge {{ $child['fee_balance'] > 0 ? 'dash-badge-warn' : 'dash-badge-ok' }}">
                                {{ $child['fee_balance'] > 0 ? 'Rs '.number_format($child['fee_balance'] / 100) : 'Paid' }}
                            </span>
                        </td>
                        <td class="dash-col-actions"><a class="dash-btn dash-btn-ghost dash-btn-sm" href="{{ route('family.child', $child['id']) }}">Open</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
