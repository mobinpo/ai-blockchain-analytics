<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .header .subtitle {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .header .date-range {
            font-size: 14px;
            color: #374151;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .stat-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 12px;
            color: #9ca3af;
        }

        .positive { color: #059669; }
        .negative { color: #dc2626; }
        .neutral { color: #d97706; }

        .chart-placeholder {
            width: 100%;
            height: 400px;
            background: #f3f4f6;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 30px 0;
        }

        .chart-placeholder p {
            color: #6b7280;
            font-size: 16px;
            text-align: center;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            font-size: 11px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
        }

        .data-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .section {
            margin: 40px 0;
        }

        .section h2 {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 15px;
            border-left: 4px solid #3b82f6;
            padding-left: 15px;
        }

        .insights {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .insights h3 {
            color: #1e40af;
            margin-bottom: 10px;
        }

        .insights ul {
            list-style-type: disc;
            margin-left: 20px;
        }

        .insights li {
            margin-bottom: 8px;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {
            .container {
                padding: 0;
            }
            
            .chart-placeholder {
                height: 300px;
            }
        }

        /* Chart styles for browserless rendering */
        .chart-container {
            width: 100%;
            height: 400px;
            margin: 20px 0;
        }

        .chart-container canvas {
            max-width: 100%;
            height: auto;
        }
    </style>
    
    @if(request()->has('engine') && request()->input('engine') === 'browserless')
    <!-- Include Chart.js for browserless rendering -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    @endif
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $title }}</h1>
            <div class="subtitle">{{ ucfirst($coin_name) }} Analysis</div>
            <div class="date-range">{{ $date_range['formatted'] }}</div>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Correlation Score</h3>
                <div class="value {{ 
                    floatval($correlation_stats['correlation_score']) > 0.3 ? 'positive' : 
                    (floatval($correlation_stats['correlation_score']) < -0.3 ? 'negative' : 'neutral') 
                }}">
                    {{ $correlation_stats['correlation_score'] }}
                </div>
                <div class="label">
                    @php
                        $score = floatval($correlation_stats['correlation_score']);
                        $strength = abs($score) >= 0.7 ? 'Strong' : (abs($score) >= 0.4 ? 'Moderate' : (abs($score) >= 0.2 ? 'Weak' : 'Very Weak'));
                        $direction = $score > 0 ? 'Positive' : ($score < 0 ? 'Negative' : 'Neutral');
                    @endphp
                    {{ $strength }} {{ $direction }}
                </div>
            </div>

            <div class="stat-card">
                <h3>Average Sentiment</h3>
                <div class="value {{ 
                    $correlation_stats['avg_sentiment'] > 0.2 ? 'positive' : 
                    ($correlation_stats['avg_sentiment'] < -0.2 ? 'negative' : 'neutral') 
                }}">
                    {{ number_format($correlation_stats['avg_sentiment'], 3) }}
                </div>
                <div class="label">
                    @php
                        $sentiment = $correlation_stats['avg_sentiment'];
                        $sentimentLabel = $sentiment > 0.6 ? 'Very Positive' : 
                                        ($sentiment > 0.2 ? 'Positive' : 
                                        ($sentiment > -0.2 ? 'Neutral' : 
                                        ($sentiment > -0.6 ? 'Negative' : 'Very Negative')));
                    @endphp
                    {{ $sentimentLabel }}
                </div>
            </div>

            <div class="stat-card">
                <h3>Price Change</h3>
                <div class="value {{ 
                    floatval($correlation_stats['price_change_percent']) > 0 ? 'positive' : 
                    (floatval($correlation_stats['price_change_percent']) < 0 ? 'negative' : 'neutral') 
                }}">
                    {{ $correlation_stats['price_change_percent'] }}%
                </div>
                <div class="label">Period Total</div>
            </div>

            <div class="stat-card">
                <h3>Data Points</h3>
                <div class="value neutral">{{ $correlation_stats['data_points'] }}</div>
                <div class="label">Days Analyzed</div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="section">
            <h2>Sentiment vs Price Timeline</h2>
            
            @if(request()->has('engine') && request()->input('engine') === 'browserless')
            <!-- Interactive Chart for Browserless -->
            <div class="chart-container">
                <canvas id="sentimentChart"></canvas>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx = document.getElementById('sentimentChart').getContext('2d');
                    const chartData = @json($chart_data);
                    
                    const chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: chartData.map(d => new Date(d.date).toLocaleDateString()),
                            datasets: [
                                {
                                    label: 'Sentiment Score',
                                    data: chartData.map(d => d.sentiment_data?.average_sentiment || 0),
                                    borderColor: 'rgb(59, 130, 246)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    yAxisID: 'y',
                                    tension: 0.3
                                },
                                {
                                    label: 'Price (USD)',
                                    data: chartData.map(d => d.price_data?.price_avg || 0),
                                    borderColor: 'rgb(16, 185, 129)',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)', 
                                    yAxisID: 'y1',
                                    tension: 0.3
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: 'Date'
                                    }
                                },
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Sentiment Score'
                                    },
                                    min: -1,
                                    max: 1
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Price (USD)'
                                    },
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            }
                        }
                    });
                });
            </script>
            @else
            <!-- Static Chart Placeholder for DomPDF -->
            <div class="chart-placeholder">
                <p>Chart visualization not available in DomPDF mode.<br>
                   Use 'browserless' engine for interactive charts.<br>
                   See data table below for detailed information.</p>
            </div>
            @endif
        </div>

        <!-- Data Table -->
        <div class="section">
            <h2>Detailed Data</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Sentiment Score</th>
                        <th>Price (USD)</th>
                        <th>Price Change %</th>
                        <th>Posts Count</th>
                        <th>Correlation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($chart_data as $dataPoint)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($dataPoint['date'])->format('M d, Y') }}</td>
                        <td class="{{ 
                            ($dataPoint['sentiment_data']['average_sentiment'] ?? 0) > 0.2 ? 'positive' : 
                            (($dataPoint['sentiment_data']['average_sentiment'] ?? 0) < -0.2 ? 'negative' : 'neutral') 
                        }}">
                            {{ number_format($dataPoint['sentiment_data']['average_sentiment'] ?? 0, 3) }}
                        </td>
                        <td>${{ number_format($dataPoint['price_data']['price_avg'] ?? 0, 2) }}</td>
                        <td class="{{ 
                            ($dataPoint['price_data']['price_change_percent'] ?? 0) > 0 ? 'positive' : 
                            (($dataPoint['price_data']['price_change_percent'] ?? 0) < 0 ? 'negative' : 'neutral') 
                        }}">
                            {{ number_format($dataPoint['price_data']['price_change_percent'] ?? 0, 2) }}%
                        </td>
                        <td>{{ $dataPoint['sentiment_data']['total_posts'] ?? 0 }}</td>
                        <td>{{ number_format($dataPoint['correlation_score'] ?? 0, 3) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Analysis Insights -->
        <div class="section">
            <h2>Key Insights</h2>
            <div class="insights">
                <h3>Correlation Analysis</h3>
                <ul>
                    @php
                        $correlationScore = floatval($correlation_stats['correlation_score']);
                        $avgSentiment = $correlation_stats['avg_sentiment'];
                        $priceChange = floatval($correlation_stats['price_change_percent']);
                    @endphp
                    
                    <li>
                        <strong>Correlation Strength:</strong> 
                        The correlation score of {{ $correlation_stats['correlation_score'] }} indicates a
                        {{ abs($correlationScore) >= 0.7 ? 'strong' : (abs($correlationScore) >= 0.4 ? 'moderate' : (abs($correlationScore) >= 0.2 ? 'weak' : 'very weak')) }}
                        {{ $correlationScore > 0 ? 'positive' : ($correlationScore < 0 ? 'negative' : 'neutral') }} 
                        relationship between sentiment and price movements.
                    </li>
                    
                    <li>
                        <strong>Sentiment Overview:</strong> 
                        Average sentiment was {{ number_format($avgSentiment, 3) }}
                        ({{ $avgSentiment > 0.6 ? 'Very Positive' : ($avgSentiment > 0.2 ? 'Positive' : ($avgSentiment > -0.2 ? 'Neutral' : ($avgSentiment > -0.6 ? 'Negative' : 'Very Negative'))) }})
                        over the analyzed period.
                    </li>
                    
                    <li>
                        <strong>Price Movement:</strong> 
                        {{ ucfirst($coin_name) }} price {{ $priceChange > 0 ? 'increased' : ($priceChange < 0 ? 'decreased' : 'remained stable') }}
                        by {{ abs($priceChange) }}% during this period.
                    </li>
                    
                    <li>
                        <strong>Data Coverage:</strong> 
                        Analysis based on {{ $correlation_stats['data_points'] }} days of data
                        from {{ implode(', ', array_map('ucfirst', $platforms)) }} platform(s).
                    </li>
                </ul>
            </div>
        </div>

        <!-- Methodology -->
        <div class="section">
            <h2>Methodology</h2>
            <p><strong>Data Sources:</strong> Social media sentiment data from Twitter, Reddit, and Telegram. Price data from CoinGecko API.</p>
            <p><strong>Sentiment Analysis:</strong> Google Cloud Natural Language Processing API with sentiment scores ranging from -1 (very negative) to +1 (very positive).</p>
            <p><strong>Correlation Calculation:</strong> Pearson correlation coefficient between daily sentiment scores and price changes.</p>
            <p><strong>Platforms Analyzed:</strong> {{ implode(', ', array_map('ucfirst', $platforms)) }}</p>
            <p><strong>Categories:</strong> {{ implode(', ', array_map('ucfirst', $categories)) }}</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Generated on {{ $generated_at }} | AI Blockchain Analytics Platform</p>
            <p>This report is for informational purposes only and should not be considered as investment advice.</p>
        </div>
    </div>
</body>
</html>