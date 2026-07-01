<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - {{ $sale->invoice_number }}</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .receipt-container {
            width: 80mm;
            background-color: #fff;
            padding: 20px;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            margin: 20px 0;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .mb-2 {
            margin-bottom: 8px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }

        .mb-6 {
            margin-bottom: 24px;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 8px;
        }

        .logo {
            height: 60px;
            width: 60px;
            object-fit: contain;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 11px;
            color: #555;
            margin: 2px 0 0 0;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .meta-info p {
            margin: 3px 0;
            font-size: 11px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .items-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 5px;
        }

        .totals-table td {
            padding: 3px 0;
        }

        .bold {
            font-weight: bold;
        }

        .footer-text {
            font-size: 10px;
            color: #555;
            margin-top: 5px;
        }

        @media print {
            body {
                background-color: #fff;
                display: block;
                padding: 0;
                margin: 0;
            }

            .receipt-container {
                width: 80mm;
                margin: 0 auto;
                padding: 10px 0;
                box-shadow: none;
            }

            .no-print {
                display: none;
            }
        }

        .print-btn-container {
            margin-bottom: 20px;
            text-align: center;
        }

        .print-btn {
            background-color: #0d9488;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-family: sans-serif;
            font-weight: bold;
            cursor: pointer;
            margin-right: 6px;
        }

        .close-btn {
            background-color: #4b5563;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-family: sans-serif;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <div class="receipt-container">
        <!-- Floating controller bar, only visible when viewing on screen -->
        <div class="print-btn-container no-print">
            <button class="print-btn" onclick="window.print()">Cetak Ulang</button>
            <button class="close-btn" onclick="window.close()">Tutup</button>
        </div>

        <div class="text-center mb-4">
            <div class="logo-container">
                <img src="/images/logo.png" alt="Logo" class="logo" onerror="this.style.display='none'">
            </div>
            <h2 class="title">{{ $sale->branch?->name ?? 'Smart POS' }}</h2>
            @if($sale->branch)
            <p class="subtitle" style="font-size: 11px; margin: 2px 0 0 0;">
                {{ $sale->branch->address }}
                @if($sale->branch->phone)
                <br>Telp: {{ $sale->branch->phone }}
                @endif
                @if($sale->branch->city)
                <br>{{ $sale->branch->city }}
                @endif
            </p>
            @endif
            <p class="subtitle">Struk Pembayaran</p>
        </div>
        
        <div class="meta-info">
            <p><strong>No. Invoice:</strong> {{ $sale->invoice_number }}</p>
            <p><strong>Tanggal:</strong> {{ $sale->sale_date->format('d/m/Y H:i') }}</p>
            <p><strong>Kasir:</strong> {{ $sale->user->name }}</p>
            <p><strong>Pelanggan:</strong> {{ $sale->customer_name ?? 'Umum' }}</p>
        </div>

        <div class="divider"></div>

        <table class="items-table">
            <tbody>
                @foreach($sale->items as $item)
                <tr>
                    <td>
                        {{ $item->product_name }}<br>
                        <span>{{ $item->quantity }} x Rp {{ number_format($item->price, 0, ',', '.') }}</span>
                    </td>
                    <td class="text-right font-mono" style="vertical-align: bottom;">
                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="divider"></div>

        <table class="totals-table">
            <tbody>
                <tr>
                    <td>Subtotal</td>
                    <td class="text-right font-mono">Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</td>
                </tr>
                @if($sale->discount_amount > 0)
                <tr>
                    <td>Diskon ({{ number_format($sale->discount_percent, 0) }}%)</td>
                    <td class="text-right font-mono">-Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</td>
                </tr>
                @endif
                @if($sale->tax_amount > 0)
                <tr>
                    <td>Pajak ({{ number_format($sale->tax_percent, 0) }}%)</td>
                    <td class="text-right font-mono">Rp {{ number_format($sale->tax_amount, 0, ',', '.') }}</td>
                </tr>
                @endif
                <tr class="bold">
                    <td>Total</td>
                    <td class="text-right font-mono">Rp {{ number_format($sale->total_amount, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Metode Bayar</td>
                    <td class="text-right">{{ $sale->payment_method_label }}</td>
                </tr>
            </tbody>
        </table>
        
        @if($sale->notes)
        <div class="divider"></div>
        <div style="font-size: 10px; margin-top: 4px;">
            <strong>Catatan:</strong> {{ $sale->notes }}
        </div>
        @endif

        <div class="divider"></div>

        <div class="text-center footer-text">
            <p>Terima kasih atas kunjungan Anda! </p>
            <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() {
                window.close();
            }, 300);
        };
    </script>
</body>

</html>
