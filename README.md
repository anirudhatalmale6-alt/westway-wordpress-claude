# Westway Loft Conversion - WordPress theme + Claude page builder

- `westway/` - block theme built from the Westway homepage design (header, footer, styles).
- `westway-site/` - plugin: the Westway section library (also in the editor under "Westway sections"), Site details, quote and guide forms (saved under Westway → Enquiries), and **Create with Claude**, which writes new pages and blog posts from the same sections and saves them as drafts.
- `dist/` - installable zips. `blueprint.json` - opens the whole site in WordPress Playground.

Claude runs on the site owner's own Anthropic API key (Westway → Claude settings). Pages are always saved as drafts; anything Claude cannot know (prices, guarantees, memberships, reviews) is left as a [placeholder].
