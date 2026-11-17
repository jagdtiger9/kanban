#!/bin/sh
# Claude Code: add the kanban MCP server for the current user (all projects).
claude mcp add --transport http --scope user kanban '<URL>' \
  --header 'Authorization: Bearer <TOKEN>'
