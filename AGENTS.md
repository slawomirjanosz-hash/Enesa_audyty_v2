# Table UI convention

Every interactive data table must support ascending and descending sorting on all data columns. Do not sort selection or action columns, PDF/email layout tables, or user-authored rich-text tables. Use the shared `public/js/table-sort.js` mechanism loaded by application layouts. Keep section boundaries and totals in place. For paginated datasets implement allowlisted server-side sorting before pagination; preserve access scopes and filters. Use raw sort values for ambiguous dates, numbers, amounts and file sizes. Test sorting and permission boundaries when changing tables.

# Deployment convention

After requested application changes, run relevant tests, commit and push the scoped changes to GitHub, and verify the resulting Railway deployments. The user requests this by default unless they explicitly ask not to deploy. Pause and explain any material risk, failed checks, conflicting remote changes, or deployment blocker before proceeding. Never include unrelated local files or secrets. Distinguish a successful push from a verified deployment.
