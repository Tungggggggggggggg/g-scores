<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>G-Scores</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
</head>

<body class="bg-slate-50 text-slate-900">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold">G-Scores</h1>
                <p class="text-sm text-slate-600">Tra cứu điểm thi theo SBD, xem phổ điểm và top 10 khối A.</p>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 bg-white rounded-xl border border-slate-200 p-4">
                <h2 class="font-semibold">Tra cứu theo SBD</h2>

                @if (!empty($datasetNotReady))
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Dữ liệu đang được import. Một số chức năng sẽ tạm thời bị khóa cho đến khi import hoàn tất.
                    </div>
                @endif

                <form id="lookup-form"
                    data-lookup-json-url="{{ route('lookup.json', [], false) }}"
                    data-home-url="{{ route('home', [], false) }}" method="POST" action="{{ route('lookup', [], false) }}" class="mt-3 flex flex-col gap-3">
                    @csrf
                    <div>
                        <label for="sbd" class="block text-sm font-medium text-slate-700">Số báo danh</label>
                        <input id="sbd" name="sbd" value="{{ old('sbd', $sbd ?? '') }}" placeholder="Ví dụ: 01000001" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900/10" />
                        @error('sbd')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                        <div id="lookup-error" class="mt-1 text-sm text-red-600 {{ empty($lookupError) ? 'hidden' : '' }}">{{ $lookupError }}</div>
                    </div>

                    <button id="lookup-submit" type="submit" class="rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800">Tra cứu</button>
                </form>

                <div class="mt-4">
                    <h3 class="text-sm font-semibold text-slate-700">Kết quả</h3>

                    <div id="result-table" class="mt-2 overflow-x-auto {{ $examResult ? '' : 'hidden' }}">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-slate-500">
                                <tr>
                                    <th class="py-2 pr-4">Môn</th>
                                    <th class="py-2">Điểm</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr>
                                    <td class="py-2 pr-4">Toán</td>
                                    <td id="score-toan" class="py-2">{{ $examResult?->toan ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Ngữ văn</td>
                                    <td id="score-ngu_van" class="py-2">{{ $examResult?->ngu_van ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Ngoại ngữ</td>
                                    <td id="score-ngoai_ngu" class="py-2">{{ $examResult?->ngoai_ngu ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Vật lí</td>
                                    <td id="score-vat_li" class="py-2">{{ $examResult?->vat_li ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Hóa học</td>
                                    <td id="score-hoa_hoc" class="py-2">{{ $examResult?->hoa_hoc ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Sinh học</td>
                                    <td id="score-sinh_hoc" class="py-2">{{ $examResult?->sinh_hoc ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Lịch sử</td>
                                    <td id="score-lich_su" class="py-2">{{ $examResult?->lich_su ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Địa lí</td>
                                    <td id="score-dia_li" class="py-2">{{ $examResult?->dia_li ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">GDCD</td>
                                    <td id="score-gdcd" class="py-2">{{ $examResult?->gdcd ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 pr-4">Mã ngoại ngữ</td>
                                    <td id="score-ma_ngoai_ngu" class="py-2">{{ $examResult?->ma_ngoai_ngu ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="result-empty" class="mt-2 text-sm text-slate-500 {{ $examResult ? 'hidden' : '' }}">
                        @if (!empty($datasetNotReady))
                            Dữ liệu đang được import.
                        @else
                            Nhập SBD để xem điểm.
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 flex flex-col gap-6">
                @if (empty($datasetNotReady))
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="font-semibold">Báo cáo phổ điểm theo môn</h2>
                            <div class="text-xs text-slate-500">4 mức: &gt;=8, 6-&lt;8, 4-&lt;6, &lt;4</div>
                        </div>
                        <div class="mt-4 h-64 sm:h-80">
                            <canvas id="distributionChart" class="w-full h-full"></canvas>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <h2 class="font-semibold">Top 10 khối A (Toán + Lý + Hóa)</h2>
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="text-left text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-4">#</th>
                                        <th class="py-2 pr-4">SBD</th>
                                        <th class="py-2 pr-4">Toán</th>
                                        <th class="py-2 pr-4">Vật lí</th>
                                        <th class="py-2 pr-4">Hóa học</th>
                                        <th class="py-2">Tổng</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($top10GroupA as $idx => $row)
                                    <tr>
                                        <td class="py-2 pr-4 text-slate-500">{{ $idx + 1 }}</td>
                                        <td class="py-2 pr-4 font-medium">{{ $row['sbd'] }}</td>
                                        <td class="py-2 pr-4">{{ $row['toan'] }}</td>
                                        <td class="py-2 pr-4">{{ $row['vat_li'] }}</td>
                                        <td class="py-2 pr-4">{{ $row['hoa_hoc'] }}</td>
                                        <td class="py-2 font-semibold">{{ $row['total'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <h2 class="font-semibold">Báo cáo & Top 10</h2>
                        <div class="mt-2 text-sm text-slate-600">
                            Dữ liệu đang được import. Báo cáo phổ điểm và top 10 sẽ khả dụng sau khi import hoàn tất.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if (empty($datasetNotReady))
        <script id="distribution-data" type="application/json">{!! json_encode($distribution, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

        <script>
            const distribution = JSON.parse(document.getElementById('distribution-data').textContent);
            const ctx = document.getElementById('distributionChart');

            const datasets = [{
                    label: '>= 8',
                    data: distribution.buckets['>= 8'],
                    backgroundColor: 'rgba(15, 118, 110, 0.8)'
                },
                {
                    label: '6 - < 8',
                    data: distribution.buckets['6 - < 8'],
                    backgroundColor: 'rgba(37, 99, 235, 0.8)'
                },
                {
                    label: '4 - < 6',
                    data: distribution.buckets['4 - < 6'],
                    backgroundColor: 'rgba(245, 158, 11, 0.85)'
                },
                {
                    label: '< 4',
                    data: distribution.buckets['< 4'],
                    backgroundColor: 'rgba(220, 38, 38, 0.8)'
                },
            ];

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: distribution.labels,
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                    },
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true
                        },
                    },
                },
            });
        </script>
    @endif

    <script>
        const lookupForm = document.getElementById('lookup-form');
        const lookupInput = document.getElementById('sbd');
        const lookupError = document.getElementById('lookup-error');
        const lookupSubmit = document.getElementById('lookup-submit');
        const resultTable = document.getElementById('result-table');
        const resultEmpty = document.getElementById('result-empty');

        const scoreCells = {
            toan: document.getElementById('score-toan'),
            ngu_van: document.getElementById('score-ngu_van'),
            ngoai_ngu: document.getElementById('score-ngoai_ngu'),
            vat_li: document.getElementById('score-vat_li'),
            hoa_hoc: document.getElementById('score-hoa_hoc'),
            sinh_hoc: document.getElementById('score-sinh_hoc'),
            lich_su: document.getElementById('score-lich_su'),
            dia_li: document.getElementById('score-dia_li'),
            gdcd: document.getElementById('score-gdcd'),
            ma_ngoai_ngu: document.getElementById('score-ma_ngoai_ngu'),
        };

        function setLookupError(message) {
            if (!lookupError) return;
            lookupError.textContent = message || '';
            lookupError.classList.toggle('hidden', !message);
        }

        function setLoading(isLoading) {
            if (!lookupSubmit) return;
            lookupSubmit.disabled = isLoading;
            lookupSubmit.textContent = isLoading ? 'Đang tra cứu...' : 'Tra cứu';
            lookupSubmit.classList.toggle('opacity-70', isLoading);
            lookupSubmit.classList.toggle('cursor-not-allowed', isLoading);
        }

        function setResult(details) {
            if (!resultTable || !resultEmpty) return;
            const hasData = Boolean(details);
            resultTable.classList.toggle('hidden', !hasData);
            resultEmpty.classList.toggle('hidden', hasData);

            if (!hasData) return;

            for (const key in scoreCells) {
                const cell = scoreCells[key];
                if (!cell) continue;
                const val = details[key];
                cell.textContent = val === null || typeof val === 'undefined' ? '-' : String(val);
            }
        }

        if (lookupForm && lookupInput && window.fetch) {
            lookupForm.addEventListener('submit', async (event) => {
                if (!lookupForm.dataset.lookupJsonUrl) return;
                event.preventDefault();

                setLookupError('');
                setLoading(true);

                const token = lookupForm.querySelector('input[name="_token"]')?.value || '';
                const sbd = (lookupInput.value || '').trim();

                try {
                    const response = await fetch(lookupForm.dataset.lookupJsonUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new URLSearchParams({
                            sbd
                        }).toString(),
                    });

                    const data = await response.json().catch(() => null);

                    if (!response.ok) {
                        setResult(null);
                        setLookupError(data?.message || 'Có lỗi xảy ra.');
                        return;
                    }

                    setResult(data?.details || null);
                    const homeUrl = lookupForm.dataset.homeUrl || '/';
                    history.replaceState(null, '', homeUrl + '?sbd=' + encodeURIComponent(sbd));
                } catch (e) {
                    setResult(null);
                    setLookupError('Không thể kết nối server.');
                } finally {
                    setLoading(false);
                }
            });
        }
    </script>
</body>

</html>