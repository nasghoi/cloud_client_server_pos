<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Downloads — Cloud Server</title>
    <meta name="description" content="List of all files downloaded from restaurant clients.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --black: #000000;
            --white: #ffffff;
            --gray-50: #f9f9f9;
            --gray-100: #f0f0f0;
            --gray-200: #e0e0e0;
            --gray-400: #9e9e9e;
            --gray-600: #5a5a5a;
            --gray-800: #1a1a1a;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--white);
            color: var(--black);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            border-bottom: 1px solid var(--black);
            padding: 16px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-brand {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .header-nav {
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .header-nav a {
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--gray-600);
            text-decoration: none;
            transition: color 0.15s;
        }

        .header-nav a:hover,
        .header-nav a.active {
            color: var(--black);
        }

        main {
            flex: 1;
            padding: 40px;
        }

        .page-header {
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }

        .page-header h1 {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }

        .page-header p {
            font-size: 13px;
            color: var(--gray-600);
        }

        .file-count {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--gray-400);
        }

        /* ── TABLE ── */
        .table-wrapper {
            border: 1px solid var(--black);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--black);
            color: var(--white);
        }

        thead th {
            padding: 12px 20px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        tbody tr {
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.1s;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: var(--gray-50);
        }

        td {
            padding: 14px 20px;
            font-size: 13px;
            vertical-align: middle;
        }

        .file-name {
            font-weight: 500;
        }

        .file-client {
            display: inline-block;
            padding: 2px 8px;
            background: var(--gray-100);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .file-size {
            font-family: monospace;
            font-size: 12px;
            color: var(--gray-600);
        }

        .file-date {
            font-size: 12px;
            color: var(--gray-600);
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            padding: 80px 40px;
            text-align: center;
            border: 1px solid var(--gray-200);
        }

        .empty-state svg {
            margin: 0 auto 16px;
            display: block;
            opacity: 0.25;
        }

        .empty-state h2 {
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 13px;
            color: var(--gray-600);
        }

        @media (max-width: 768px) {
            header { padding: 16px 20px; }
            main   { padding: 24px 20px; }

            table thead th:nth-child(3),
            table tbody td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>

    <header>
        <span class="header-brand">Cloud Server</span>
        <nav class="header-nav">
            <a href="{{ url('/') }}">Control Panel</a>
            <a href="{{ url('/downloads') }}" class="active">Downloads</a>
        </nav>
    </header>

    <main>
        <div class="page-header">
            <div>
                <h1>Downloads</h1>
                <p>Files received from restaurant clients via outbound stream.</p>
            </div>
            <span class="file-count">{{ count($files) }} {{ Str::plural('file', count($files)) }}</span>
        </div>

        @if (count($files) > 0)
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Client ID</th>
                            <th>Size</th>
                            <th>Last Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td class="file-name">{{ $file['name'] }}</td>
                                <td><span class="file-client">{{ $file['client'] }}</span></td>
                                <td class="file-size">{{ $file['size'] }}</td>
                                <td class="file-date">{{ $file['modified'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M3 16v2a2 2 0 002 2h14a2 2 0 002-2v-2"/>
                    <path d="M8 12l4 4 4-4"/>
                    <path d="M12 3v13"/>
                </svg>
                <h2>No files downloaded yet</h2>
                <p>Trigger a download from the Control Panel to see files here.</p>
            </div>
        @endif
    </main>

</body>
</html>
