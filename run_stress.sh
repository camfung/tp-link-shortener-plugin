#!/usr/bin/env bash
# =============================================================================
# Stress Pipeline Orchestrator
# =============================================================================
# Runs the complete stress test pipeline: link creation, usage generation,
# and dashboard verification -- sequentially with a shared RUN_ID.
#
# Usage:
#   ./run_stress.sh                # Generate a random RUN_ID
#   ./run_stress.sh stress-abc123  # Use a specific RUN_ID
#
# Environment variables:
#   STRESS_RUN_ID        - Override run ID (default: random hex)
#   STRESS_LINK_COUNT    - Number of links to create (default: 50)
#   STRESS_HITS_PER_LINK - Hits per link during usage generation (default: 5)
#   STRESS_RATE_LIMIT    - Delay between requests in seconds (default: 1.5)
#   STRESS_POLL_TIMEOUT  - Dashboard verification timeout in seconds (default: 120)
#   TP_SHORT_DOMAIN      - Short link domain (default: dev.trfc.link)
#   TP_API_ENDPOINT      - API endpoint for cleanup (required for cleanup)
#   API_KEY              - API key for cleanup (required for cleanup)
# =============================================================================
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")" && pwd)"

# ---- RUN_ID ----------------------------------------------------------------
if [[ -n "${1:-}" ]]; then
    RUN_ID="$1"
else
    RUN_ID="stress-$(openssl rand -hex 4)"
fi
export STRESS_RUN_ID="$RUN_ID"

# ---- Config defaults --------------------------------------------------------
LINK_COUNT="${STRESS_LINK_COUNT:-50}"
HITS_PER_LINK="${STRESS_HITS_PER_LINK:-5}"

# ---- Banner -----------------------------------------------------------------
echo "============================================================"
echo "  STRESS PIPELINE"
echo "============================================================"
echo "  RUN_ID:         $RUN_ID"
echo "  Started:        $(date -u +"%Y-%m-%d %H:%M:%S UTC")"
echo "  Link count:     $LINK_COUNT"
echo "  Hits per link:  $HITS_PER_LINK"
echo "============================================================"
echo ""

# ---- Stage 1: Link Creation ------------------------------------------------
echo ">> Stage 1/3: Creating links..."
echo "------------------------------------------------------------"
if ! (cd "$PROJECT_ROOT/tests/e2e" && python -m pytest stress/test_create_links.py -m stress -v -s --timeout=600); then
    echo ""
    echo "FAILED: Link creation (Stage 1/3)"
    exit 1
fi
echo ""

# ---- Stage 2: Usage Generation ---------------------------------------------
echo ">> Stage 2/3: Generating usage traffic..."
echo "------------------------------------------------------------"
if ! (cd "$PROJECT_ROOT/tests/e2e" && python -m pytest stress/test_generate_usage.py -m stress -v -s --timeout=900); then
    echo ""
    echo "FAILED: Usage generation (Stage 2/3)"
    exit 1
fi
echo ""

# ---- Stage 3: Dashboard Verification ----------------------------------------
echo ">> Stage 3/3: Verifying dashboard..."
echo "------------------------------------------------------------"
if ! (cd "$PROJECT_ROOT/tests/e2e" && python -m pytest stress/test_verify_dashboard.py -m stress -v -s --timeout=300); then
    echo ""
    echo "FAILED: Dashboard verification (Stage 3/3)"
    exit 1
fi
echo ""

# ---- Success ----------------------------------------------------------------
echo "============================================================"
echo "  ALL STAGES PASSED"
echo "============================================================"
echo "  RUN_ID:    $RUN_ID"
echo "  Finished:  $(date -u +"%Y-%m-%d %H:%M:%S UTC")"
echo "============================================================"
echo ""

# ---- Cleanup prompt ---------------------------------------------------------
read -rp "Run cleanup? (y/N) " answer
case "${answer,,}" in
    y|yes)
        echo "Running cleanup for $RUN_ID..."
        python "$PROJECT_ROOT/tests/e2e/scripts/cleanup_stress.py" "$RUN_ID"
        ;;
    *)
        echo "Skipping cleanup. Run manually:"
        echo "  python tests/e2e/scripts/cleanup_stress.py $RUN_ID"
        ;;
esac
