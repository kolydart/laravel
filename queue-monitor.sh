#!/bin/bash

# Laravel MySQL Database Queue Monitoring Script
# This script provides real-time monitoring of Laravel database queues
# Optimized specifically for MySQL database queue driver

#==============================================================================
# QUICK REFERENCE
#==============================================================================
#
# USAGE:
#   ./queue-monitor.sh [options]
#
# OPTIONS:
#   -q, --queue QUEUE        Queue name (default: default)
#   -r, --refresh SECONDS    Refresh interval (default: 5)
#   -p, --project NAME       Project name for display
#   -h, --help               Show help
#
# EXAMPLES:
#   ./queue-monitor.sh                           # Use defaults (database connection)
#   ./queue-monitor.sh -q emails                 # Monitor emails queue
#   ./queue-monitor.sh -r 10 -p "My App"        # 10 second refresh, custom name
#   QUEUE_NAME=emails ./queue-monitor.sh         # Use environment variable
#
# ENVIRONMENT VARIABLES:
#   QUEUE_NAME               Queue name (default, emails, etc.)
#   QUEUE_REFRESH_INTERVAL   Refresh interval in seconds
#   PROJECT_NAME             Project name for display
#   LARAVEL_COMMAND          Laravel command (php artisan, sail artisan, etc.)
#
# INTERACTIVE COMMANDS (while running):
#   q, quit    - Exit monitor
#   r, refresh - Refresh now
#   f, failed  - Show all failed jobs
#   c, clear   - Clear failed jobs (with confirmation)
#   w, worker  - Start queue worker
#   s, status  - Show queue status
#   h, help    - Show help
#
# FEATURES:
#   - Real-time MySQL database queue monitoring with auto-refresh
#   - Shows pending, failed, and processing job counts
#   - Displays recent jobs with status and attempts
#   - Interactive controls for managing jobs and workers
#   - Auto-detects Laravel command (php artisan, sail artisan, cphp artisan)
#   - Optimized MySQL queries for better performance
#   - Shows queue-specific statistics and job distribution
#   - Configurable via command line or environment variables
#
# REQUIREMENTS:
#   - Laravel project with MySQL database
#   - Queue tables migrated (jobs, failed_jobs)
#   - Queue connection set to 'database' in config/queue.php
#   - Bash shell
#   - Access to Laravel artisan command
#
# MYSQL TABLES USED:
#   - jobs (pending and processing jobs)
#   - failed_jobs (failed job records)
#
#==============================================================================

# Configuration - Edit these for your environment
REFRESH_INTERVAL=${QUEUE_REFRESH_INTERVAL:-5}
QUEUE_NAME=${QUEUE_NAME:-"default"}
LARAVEL_COMMAND=${LARAVEL_COMMAND:-"php artisan"}  # or "sail artisan", "cphp artisan", etc.
PROJECT_NAME=${PROJECT_NAME:-"Laravel Database Queue"}

# Auto-detect common Laravel command variations
if command -v sail &> /dev/null && [ -f "docker-compose.yml" ]; then
    LARAVEL_COMMAND="sail artisan"
elif command -v cphp &> /dev/null; then
    LARAVEL_COMMAND="cphp artisan"
elif [ -f "artisan" ]; then
    LARAVEL_COMMAND="php artisan"
else
    echo "Error: Cannot find Laravel artisan command"
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Function to clear screen
clear_screen() {
    clear
}

# Function to get queue statistics optimized for MySQL
get_queue_stats() {
    local stats=$(${LARAVEL_COMMAND} tinker --execute="
    \$pending = DB::table('jobs')->where('queue', '${QUEUE_NAME}')->count();
    \$failed = DB::table('failed_jobs')->where('queue', '${QUEUE_NAME}')->count();
    \$processing = DB::table('jobs')->where('queue', '${QUEUE_NAME}')->whereNotNull('reserved_at')->count();
    \$total_pending = DB::table('jobs')->count();
    \$total_failed = DB::table('failed_jobs')->count();
    echo \$pending . ':' . \$failed . ':' . \$processing . ':' . \$total_pending . ':' . \$total_failed;
    " 2>/dev/null | tail -1)

    echo "${stats:-0:0:0:0:0}"
}

# Function to get recent jobs from MySQL database
get_recent_jobs() {
    ${LARAVEL_COMMAND} tinker --execute="
    \$jobs = DB::table('jobs')
        ->where('queue', '${QUEUE_NAME}')
        ->orderBy('id', 'desc')
        ->limit(5)
        ->get();
    foreach (\$jobs as \$job) {
        \$payload = json_decode(\$job->payload, true);
        \$jobClass = \$payload['displayName'] ?? 'Unknown';
        \$attempts = \$job->attempts ?? 0;
        \$reserved = \$job->reserved_at ? 'PROCESSING' : 'PENDING';
        \$priority = \$job->priority ?? 0;
        echo \$job->id . '|' . \$jobClass . '|' . \$attempts . '|' . \$reserved . '|' . \$job->created_at . '|' . \$priority . PHP_EOL;
    }
    " 2>/dev/null | grep -v "^$"
}

# Function to get recent failed jobs from MySQL database
get_recent_failed_jobs() {
    ${LARAVEL_COMMAND} tinker --execute="
    \$failed = DB::table('failed_jobs')
        ->where('queue', '${QUEUE_NAME}')
        ->orderBy('id', 'desc')
        ->limit(3)
        ->get();
    foreach (\$failed as \$job) {
        \$payload = json_decode(\$job->payload, true);
        \$jobClass = \$payload['displayName'] ?? 'Unknown';
        \$exception = substr(\$job->exception, 0, 50) . '...';
        echo \$job->id . '|' . \$jobClass . '|' . \$job->failed_at . '|' . \$exception . PHP_EOL;
    }
    " 2>/dev/null | grep -v "^$"
}

# Function to get currently running job from MySQL database
get_current_running_job() {
    ${LARAVEL_COMMAND} tinker --execute="
    \$job = DB::table('jobs')
        ->where('queue', '${QUEUE_NAME}')
        ->whereNotNull('reserved_at')
        ->orderBy('reserved_at', 'desc')
        ->first();
    if (\$job) {
        \$payload = json_decode(\$job->payload, true);
        \$jobClass = \$payload['displayName'] ?? 'Unknown';
        \$uuid = \$payload['uuid'] ?? 'N/A';
        \$attempts = \$job->attempts ?? 0;
        \$reservedAt = date('Y-m-d H:i:s', \$job->reserved_at);
        echo \$job->id . '|' . \$uuid . '|' . \$jobClass . '|' . \$attempts . '|' . \$reservedAt . PHP_EOL;
    }
    " 2>/dev/null | grep -v "^$"
}

# Function to display header
display_header() {
    local inner_width=77 # Width inside the borders

    # Line 1: Top border
    echo -e "${BLUE}╔$(printf '%.0s═' $(seq 1 $inner_width))╗${NC}"

    # Line 2: Project Name
    local project_base_text="${PROJECT_NAME} Queue Monitor"
    local project_visible_len=${#project_base_text}

    if (( project_visible_len > inner_width )); then
        project_base_text="${project_base_text:0:$((inner_width - 3))}..." # Truncate and add ellipsis
        project_visible_len=${#project_base_text}
    fi

    local project_padding_needed=$(( inner_width - project_visible_len ))
    local project_pad_left=$(( project_padding_needed / 2 ))
    local project_pad_right=$(( project_padding_needed - project_pad_left ))

    local line2="${BLUE}║${NC}"
    line2+=$(printf "%*s" "$project_pad_left" "")
    line2+="${CYAN}${project_base_text}${NC}"
    line2+=$(printf "%*s" "$project_pad_right" "")
    line2+="${BLUE}║${NC}"
    echo -e "$line2"

    # Line 3: Updated Timestamp
    local updated_text="Updated: $(date '+%Y-%m-%d %H:%M:%S')"
    local updated_visible_len=${#updated_text}

    local updated_padding_needed=$(( inner_width - updated_visible_len ))
    local updated_pad_left=$(( updated_padding_needed / 2 ))
    local updated_pad_right=$(( updated_padding_needed - updated_pad_left ))

    local line3="${BLUE}║${NC}"
    line3+=$(printf "%*s" "$updated_pad_left" "")
    line3+="${updated_text}"
    line3+=$(printf "%*s" "$updated_pad_right" "")
    line3+="${BLUE}║${NC}"
    echo -e "$line3"

    # Line 4: Command
    local command_base_text="Command: ${LARAVEL_COMMAND}"
    local command_visible_len=${#command_base_text}

    if (( command_visible_len > inner_width )); then
        command_base_text="${command_base_text:0:$((inner_width - 3))}..." # Truncate and add ellipsis
        command_visible_len=${#command_base_text}
    fi

    local command_padding_needed=$(( inner_width - command_visible_len ))
    local command_pad_left=$(( command_padding_needed / 2 ))
    local command_pad_right=$(( command_padding_needed - command_pad_left ))

    local line4="${BLUE}║${NC}"
    line4+=$(printf "%*s" "$command_pad_left" "")
    line4+="${command_base_text}"
    line4+=$(printf "%*s" "$command_pad_right" "")
    line4+="${BLUE}║${NC}"
    echo -e "$line4"

    # Line 5: Bottom border
    echo -e "${BLUE}╚$(printf '%.0s═' $(seq 1 $inner_width))╝${NC}"
    echo ""
}

# Function to display MySQL queue statistics
display_stats() {
    local stats=$(get_queue_stats)
    IFS=':' read -r pending failed processing total_pending total_failed <<< "$stats"

    echo -e "${YELLOW}MySQL Database Queue Statistics:${NC}"
    echo -e "├─ ${CYAN}Queue Name:${NC}        ${QUEUE_NAME}"
    echo -e "├─ ${GREEN}Pending Jobs:${NC}      ${pending} (Total: ${total_pending})"
    echo -e "├─ ${RED}Failed Jobs:${NC}       ${failed} (Total: ${total_failed})"
    echo -e "├─ ${BLUE}Processing:${NC}        ${processing}"
    echo -e "└─ ${CYAN}Connection:${NC}        MySQL Database"
    echo ""
}

# Function to display currently running job
display_current_running_job() {
    echo -e "${YELLOW}Currently Running Job:${NC}"

    local running_job=$(get_current_running_job)
    if [ -z "$running_job" ]; then
        echo -e "${GREEN}No job currently running${NC}"
    else
        echo -e "${CYAN}ID    | UUID                                 | Job Class                    | Attempts | Reserved At${NC}"
        echo "------|--------------------------------------|------------------------------|----------|--------------------"
        while IFS='|' read -r id uuid class attempts reserved_at; do
            printf "${BLUE}%-5s${NC} | %-36s | %-28s | %-8s | %s\n" \
                "$id" "$uuid" "$(echo $class | cut -c1-28)" "$attempts" "$reserved_at"
        done <<< "$running_job"
    fi
    echo ""
}

# Function to display recent jobs from MySQL
display_recent_jobs() {
    echo -e "${YELLOW}Recent Jobs (Last 5 from '${QUEUE_NAME}' queue):${NC}"
    echo -e "${CYAN}ID    | Job Class                    | Attempts | Status     | Created At          | Priority${NC}"
    echo "------|------------------------------|----------|------------|---------------------|--------"

    local jobs=$(get_recent_jobs)
    if [ -z "$jobs" ]; then
        echo -e "${YELLOW}No recent jobs found in '${QUEUE_NAME}' queue${NC}"
    else
        while IFS='|' read -r id class attempts status created_at priority; do
            local status_color="${GREEN}"
            if [ "$status" = "PROCESSING" ]; then
                status_color="${BLUE}"
            fi
            printf "%-5s | %-28s | %-8s | ${status_color}%-10s${NC} | %-19s | %s\n" \
                "$id" "$(echo $class | cut -c1-28)" "$attempts" "$status" "$created_at" "$priority"
        done <<< "$jobs"
    fi
    echo ""
}

# Function to display recent failed jobs from MySQL
display_failed_jobs() {
    echo -e "${YELLOW}Recent Failed Jobs (Last 3 from '${QUEUE_NAME}' queue):${NC}"
    echo -e "${CYAN}ID    | Job Class                    | Failed At           | Exception${NC}"
    echo "------|------------------------------|---------------------|------------------"

    local failed_jobs=$(get_recent_failed_jobs)
    if [ -z "$failed_jobs" ]; then
        echo -e "${GREEN}No failed jobs found in '${QUEUE_NAME}' queue${NC}"
    else
        while IFS='|' read -r id class failed_at exception; do
            printf "${RED}%-5s${NC} | %-28s | %-19s | %s\n" \
                "$id" "$(echo $class | cut -c1-28)" "$failed_at" "$exception"
        done <<< "$failed_jobs"
    fi
    echo ""
}

# Function to display help
display_help() {
    echo -e "${CYAN}Commands:${NC}"
    echo "  q, quit    - Exit monitor"
    echo "  r, refresh - Refresh now"
    echo "  f, failed  - Show all failed jobs"
    echo "  c, clear   - Clear failed jobs (with confirmation)"
    echo "  s, status  - Show queue status"
    echo "  h, help    - Show this help"
    echo ""
    echo -e "${YELLOW}Press any key to refresh manually.${NC}"
    echo ""
}

# Function to show all failed jobs
show_all_failed_jobs() {
    clear_screen
    echo -e "${RED}All Failed Jobs:${NC}"
    echo "================"
    ${LARAVEL_COMMAND} queue:failed
    echo ""
    echo -e "${YELLOW}Press any key to return to monitor...${NC}"
    read -n 1 -s
}

# Function to clear failed jobs
clear_failed_jobs() {
    echo -e "${RED}WARNING: This will permanently delete all failed jobs!${NC}"
    echo -n "Are you sure? (type 'yes' to confirm): "
    read confirmation
    if [ "$confirmation" = "yes" ]; then
        ${LARAVEL_COMMAND} queue:flush
        echo -e "${GREEN}All failed jobs have been cleared.${NC}"
    else
        echo -e "${YELLOW}Operation cancelled.${NC}"
    fi
    echo -e "${YELLOW}Press any key to continue...${NC}"
    read -n 1 -s
}

# Function to start MySQL database queue worker
start_queue_worker() {
    echo -e "${GREEN}Starting MySQL database queue worker...${NC}"
    echo -e "${YELLOW}Press Ctrl+C to stop the worker and return to monitor${NC}"
    echo ""
    ${LARAVEL_COMMAND} queue:work database --queue=${QUEUE_NAME} --verbose --timeout=60
    echo ""
    echo -e "${YELLOW}Worker stopped. Press any key to continue...${NC}"
    read -n 1 -s
}

# Function to show queue status
show_queue_status() {
    clear_screen
    echo -e "${CYAN}Queue Status:${NC}"
    echo "============="
    ${LARAVEL_COMMAND} queue:monitor
    echo ""
    echo -e "${YELLOW}Press any key to return to monitor...${NC}"
    read -n 1 -s
}

# Main monitoring loop
monitor_queue() {
    while true; do
        clear_screen
        display_header
        display_stats
        display_current_running_job
        display_recent_jobs
        display_failed_jobs
        display_help

        # Wait for input (no auto-refresh)
        read -n 1 input

        case "$input" in
            q|Q)
                echo -e "\n${GREEN}Exiting queue monitor...${NC}"
                exit 0
                ;;
            r|R)
                continue
                ;;
            f|F)
                show_all_failed_jobs
                ;;
            c|C)
                clear_failed_jobs
                ;;
            s|S)
                show_queue_status
                ;;
            h|H)
                echo -e "\n${CYAN}Help displayed above. Press any key to continue...${NC}"
                read -n 1 -s
                ;;
            *)
                # Auto-refresh or manual refresh
                continue
                ;;
        esac
    done
}

# Function to show usage
show_usage() {
    echo "Laravel MySQL Database Queue Monitor"
    echo ""
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -q, --queue QUEUE        Queue name (default: default)"
    echo "  -r, --refresh SECONDS    Refresh interval (default: 5)"
    echo "  -p, --project NAME       Project name for display"
    echo "  -h, --help               Show this help"
    echo ""
    echo "Environment Variables:"
    echo "  QUEUE_NAME               Queue name"
    echo "  QUEUE_REFRESH_INTERVAL   Refresh interval"
    echo "  PROJECT_NAME             Project name"
    echo "  LARAVEL_COMMAND          Laravel command (php artisan, sail artisan, etc.)"
    echo ""
    echo "Examples:"
    echo "  $0                                    # Use defaults (database connection)"
    echo "  $0 -q emails                         # Monitor emails queue"
    echo "  $0 -r 10 -p \"My App\"                # 10 second refresh, custom name"
    echo "  QUEUE_NAME=emails $0                  # Use environment variable"
    echo ""
    echo "Note: This script is optimized for MySQL database queues only."
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -q|--queue)
            QUEUE_NAME="$2"
            shift 2
            ;;
        -r|--refresh)
            REFRESH_INTERVAL="$2"
            shift 2
            ;;
        -p|--project)
            PROJECT_NAME="$2"
            shift 2
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Check if Laravel is ready
if ! ${LARAVEL_COMMAND} migrate:status > /dev/null 2>&1; then
    echo -e "${RED}Error: Cannot connect to Laravel application or database.${NC}"
    echo "Please ensure your Laravel application is properly configured."
    echo "Detected command: ${LARAVEL_COMMAND}"
    exit 1
fi

# Start monitoring
echo -e "${GREEN}Starting ${PROJECT_NAME} Queue Monitor...${NC}"
echo -e "${YELLOW}Press 'h' for help, 'q' to quit${NC}"
sleep 2

monitor_queue
