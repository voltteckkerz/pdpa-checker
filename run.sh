#!/bin/bash
echo "Starting PDPA Compliance Checker..."
echo "Open http://localhost:8000 in your browser"
uvicorn main:app --host 0.0.0.0 --port 8000 --reload
