#!/bin/bash

echo "🚀 AI Blockchain Analytics - 500 Concurrent Load Test Monitor"
echo "================================================================"
echo

while true; do
    clear
    echo "🚀 AI Blockchain Analytics - 500 Concurrent Load Test Monitor"
    echo "================================================================"
    echo "⏰ Current Time: $(date)"
    echo
    
    # Check if Artillery is running
    if pgrep -f "artillery run" > /dev/null; then
        echo "✅ Load Test Status: RUNNING"
    else
        echo "❌ Load Test Status: NOT RUNNING"
    fi
    
    echo
    echo "📊 System Metrics:"
    echo "=================="
    
    # Latest monitoring data
    if [ -f "monitoring_500_test.csv" ]; then
        latest=$(tail -1 monitoring_500_test.csv)
        if [ "$latest" != "timestamp,cpu_percent,memory_percent,load_1m,connections" ]; then
            timestamp=$(echo $latest | cut -d',' -f1)
            cpu=$(echo $latest | cut -d',' -f2)
            memory=$(echo $latest | cut -d',' -f3)
            load=$(echo $latest | cut -d',' -f4)
            connections=$(echo $latest | cut -d',' -f5)
            
            echo "🕒 Last Update: $timestamp"
            echo "🖥️  CPU Usage: ${cpu}%"
            echo "💾 Memory Usage: ${memory}%"
            echo "⚡ Load Average: $load"
            echo "🔗 Active Connections: $connections"
        fi
    fi
    
    echo
    echo "📈 Artillery Test Progress:"
    echo "=========================="
    
    # Check Artillery logs
    if [ -d "results" ] && ls results/*.json 1> /dev/null 2>&1; then
        latest_file=$(ls -t results/*.json | head -1)
        if [ -f "$latest_file" ]; then
            echo "📁 Results File: $(basename $latest_file)"
        fi
    fi
    
    # Show recent Artillery output
    echo "Recent Artillery Output:"
    if pgrep -f "artillery run" > /dev/null; then
        echo "  • Test is actively running..."
        echo "  • Monitoring system performance..."
    fi
    
    echo
    echo "🔍 Live Connection Monitoring:"
    echo "=============================="
    netstat_output=$(ss -t state established | wc -l)
    echo "Active TCP Connections: $netstat_output"
    
    echo
    echo "📊 Historical Metrics (Last 5 readings):"
    echo "========================================"
    if [ -f "monitoring_500_test.csv" ]; then
        tail -5 monitoring_500_test.csv | while IFS=',' read -r timestamp cpu memory load connections; do
            if [ "$timestamp" != "timestamp" ]; then
                printf "%-20s CPU: %5s%% MEM: %5s%% LOAD: %5s CONN: %s\n" \
                    "$(echo $timestamp | cut -d' ' -f2)" "$cpu" "$memory" "$load" "$connections"
            fi
        done
    fi
    
    echo
    echo "Press Ctrl+C to stop monitoring..."
    
    sleep 10
done