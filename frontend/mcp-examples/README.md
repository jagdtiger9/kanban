# Kanban MCP — подключение CLI-агентов

1. В kanban: **Настройки → MCP → Create token**. Скопируйте токен (показывается один раз).
2. Возьмите пример для своего агента и замените:
   - `<URL>` — адрес сервера из настроек, например `https://kanban.example.com/mcp`;
   - `<TOKEN>` — ваш токен `knb_...`.
3. Перезапустите агента и спросите: «Что нового на моих досках?»

Новый токен заменяет старый: агенты со старым токеном получат `401`.
Срок действия токена — 90 дней. Лимит — 60 запросов в минуту.

| Файл                   | Агент                                   |
| ---------------------- | --------------------------------------- |
| `claude-code.sh`       | Claude Code                             |
| `codex-config.toml`    | Codex CLI (`~/.codex/config.toml`)      |
| `gemini-settings.json` | Gemini CLI (`~/.gemini/settings.json`)  |
| `opencode-deepseek.json` | DeepSeek через opencode (`opencode.json`) |

Формат Claude Code проверен; остальные — по документации клиентов, при ошибке сверьте
с актуальной документацией агента.

Инструменты: `list_projects`, `get_board`, `get_card`, `whats_new`, `create_card`,
`rename_card`, `set_description`, `append_to_description`, `set_due_date`, `add_label`,
`remove_label`, `assign`, `unassign`, `move_card`, `add_comment`.
