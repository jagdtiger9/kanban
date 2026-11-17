<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

// MCP server for CLI agents (src/Mcp, decision record docs/plans/mcp.md).
return App::config([
    'parameters' => [
        // one JSON file per user with the token hash (FileMcpTokenRepository)
        'mcp.token_directory' => '%kernel.project_dir%/var/mcp-tokens',
    ],
    'when@test' => [
        'parameters' => [
            'mcp.token_directory' => '%kernel.cache_dir%/mcp-tokens',
        ],
    ],
    'mcp' => [
        'servers' => [
            'default' => [
                'name' => 'kanban',
                'instructions' => <<<'TXT'
                    Kanban boards of the authenticated user; you act with exactly their rights.

                    Model: project → board → columns → cards. A card's column IS its status
                    (e.g. To Do / In Progress / Done); there is no separate status field.
                    "Close / finish / mark done" = move_card to the done column.

                    Reading: start with list_projects, then get_board for the ids of columns,
                    labels and members. "What's new / what changed" → whats_new (default: last
                    24 hours — say which period you report). Refer to things by name, not id.

                    Before changing anything, resolve every target; ask when unsure:
                    - Project, column, assignee or label not named → ask, proposing the most
                      likely choice ("Put it in To Do? Or Done, since it is finished?").
                    - A name matches several projects, cards, columns or members → ask which.
                    - A card with the same or a similar title already exists on the board (check
                      get_board before create_card) → ask before creating a duplicate.
                    - Relative dates ("by Friday") → convert to YYYY-MM-DD and state the date.
                    - Never guess ids or invent columns, labels or members — take them from
                      get_board.
                    - Several cards at once → list the planned changes and get confirmation.
                    - Text you wrote yourself (a comment, a new description) → show it first.

                    Each write tool does one change: rename_card, set_description (replaces) /
                    append_to_description (adds), set_due_date, add_label / remove_label and
                    assign / unassign (one at a time, the others stay), move_card, add_comment.

                    Rights: any project member may create cards, move any card, set assignees,
                    labels and due dates on any card, and comment. Only the card creator or a
                    project admin may change a card's title or description — see canEdit on the
                    card. Global admins act as admins everywhere. A refusal is a real rule, not a glitch — report it, do not
                    retry or work around it.

                    Not available here: deleting or archiving cards, editing or deleting comments,
                    creating projects, columns or labels, managing members. Say so and point the
                    user to the web UI — do not substitute another action (e.g. do not move a
                    card to Done when asked to delete it).

                    Archived cards are hidden from get_board; do not change an archived card
                    unless the user explicitly asks. After every change, report briefly what was
                    done, with the card title and column.
                    TXT,
                // only the tools of the Mcp module, not services of third-party bundles
                'registry' => 'App\\Mcp\\',
                'http' => [
                    'path' => '/mcp',
                    // DNS-rebinding protection only allows localhost; the server is public
                    // and every request is authenticated by the `mcp` firewall instead.
                    'allowed_hosts' => false,
                ],
            ],
        ],
    ],
    'framework' => [
        'rate_limiter' => [
            'limiters' => [
                // per token owner: one agent answer is ~5–20 HTTP calls
                'mcp_requests' => [
                    'policy' => 'sliding_window',
                    'limit' => 60,
                    'interval' => '1 minute',
                    'lock_factory' => null,
                ],
                // per client IP: bad or missing tokens
                'mcp_failed_auth' => [
                    'policy' => 'fixed_window',
                    'limit' => 10,
                    'interval' => '1 minute',
                    'lock_factory' => null,
                ],
            ],
        ],
    ],
]);
