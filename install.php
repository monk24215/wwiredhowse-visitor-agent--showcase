<?php
/**
 * install.php — first-run setup. Creates tables, seeds the resume agent.
 * Visit ONCE in a browser after editing config.php. Then delete this file.
 */
require __DIR__ . '/_lib.php';
admin_require();

$pdo = pdo();
$messages = [];

// 1. Schema
$pdo->exec("
CREATE TABLE IF NOT EXISTS agent_agents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    greeting TEXT NULL,
    suggested_chips TEXT NULL,
    system_preamble MEDIUMTEXT NULL,
    accent_color VARCHAR(20) NOT NULL DEFAULT '#1a1a1a',
    model VARCHAR(64) NULL,
    max_tokens INT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$messages[] = "agent_agents table ready";

$pdo->exec("
CREATE TABLE IF NOT EXISTS agent_sources (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    agent_id INT UNSIGNED NOT NULL,
    label VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NOT NULL,
    priority INT NOT NULL DEFAULT 50,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(500) NULL,
    file_name VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agent_priority (agent_id, enabled, priority),
    FOREIGN KEY (agent_id) REFERENCES agent_agents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
$messages[] = "agent_sources table ready";

// 2. Seed the resume agent if it doesn't exist
$existing = agent_by_slug('resume');
if (!$existing) {
    $stmt = $pdo->prepare("
        INSERT INTO agent_agents (slug, name, greeting, suggested_chips, system_preamble, accent_color)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'resume',
        'Ask About Kristian',
        "Hi — I can answer questions about Kristian Oubre's background, projects, and experience. What would you like to know?",
        json_encode(['Experience', 'Projects', 'AI work', 'Contact']),
        "You are a professional resume assistant for Kristian M. Oubre.\n"
        . "Answer accurately based ONLY on the reference documents below. "
        . "If something isn't in the documents, say so plainly and offer related info that IS covered. "
        . "Be concise. Professional but warm. No filler. "
        . "Never fabricate experience, dates, employers, or skills. "
        . "Refer to Kristian by first name on second mention. "
        . "Decline politely to discuss anything outside Kristian's professional background.",
        '#e8a020', // amber, matches resume site
    ]);
    $agentId = (int)$pdo->lastInsertId();
    $messages[] = "Created agent: resume (id=$agentId)";

    // Seed a starter source so the agent works immediately. Replace via admin.
    $starter = <<<'TXT'
Kristian M. Oubre — Resume Snapshot

CONTACT
- Manchaca, TX 78652
- (512) 801-0177
- koubre@gmail.com
- WirehouseSolutions.com
- linkedin.com/in/kristian-oubre
- resume.wiredhowse.com

HEADLINE
Chief Operations & Strategy Officer · WordPress Developer · PHP Engineer · AI Integration Specialist.
20+ years full-stack PHP, digital operations leadership, AI-integrated marketing infrastructure.
Currently operates a 280,000-subscriber email business with 30,000 daily clicks and 57-offer ClickBank rotation.

EXPERIENCE
1) Founder & CEO — Defend Survive Prepare (Apr 2020 – Present)
   - Built DTC eCommerce + affiliate business to $22K/month recurring net revenue
   - 280K subscribers (Flodesk), 30K daily clicks, 57-offer ClickBank rotation
   - Designed end-to-end AI email pipeline (PrepperScan + Anthropic API)
   - Reduced operational costs 30%, increased customer LTV 40%

2) Chief Operating Officer — CQC International (Apr 2011 – Apr 2020)
   - 7-figure digital publishing & e-learning company
   - 60%+ revenue growth; subscribers scaled 98K → 253K in under 12 months
   - Managed 250+ web properties; reduced tech support costs 40%

3) Founder — WirehouseSolutions.com / Austin Wirehouse (Apr 2000 – Present)
   - 150+ WordPress + PHP sites delivered
   - Built BAM/WHS modular PHP framework (powers all internal apps)
   - Proprietary plugins: Marketing Accelerator, Auto-Link Keyword System
   - 70% mobile load time improvement on Core Web Vitals audits

CORE SKILLS
- PHP 8 (20yr), BAM/WHS framework, PDO, MySQL (advanced), REST APIs, cron, OOP
- WordPress (theme & plugin dev, Elementor, WooCommerce, ACF, WP-CLI, multisite, emergency recovery)
- Anthropic API (Claude), OpenAI/ChatGPT, prompt engineering at scale, web_search tool, AI content pipelines
- HTML5, CSS3, JavaScript ES6+, AJAX, Bootstrap 5, React.js, Node.js, Chrome Extensions (MV3)
- Infrastructure: cPanel/Plesk, Linux, PHP-FPM, Cloudflare, XAMPP/LEMP, DigitalOcean, SSH
- SEO: Core Web Vitals, GTmetrix, Lighthouse, Schema JSON-LD, technical SEO
- Email & Marketing: Flodesk, ActiveCampaign, ClickBank, GA4/GTM, Stripe, Google/Meta Ads

NOTABLE PROJECTS
- PrepperScan — AI news aggregator (Anthropic API + web_search, PHP CORS proxy, 9-category classification)
- Survivalist Email Copy Prompt Engine — unified prompt system, daily production against 280K list
- BAM/WHS PHP Framework — proprietary lightweight micro-framework, AI-coding-assistant friendly
- ClickBank Analytics Dashboard (cbanalytics.wiredhowse.com) — 57+ vendors, 10 time periods, dual-DB
- ClickBank Marketplace Intelligence Engine — scraper + normalized schema + REST API + diff tracking
- AI Sales Page Generator — 24-step structured prompt framework, Elementor-ready JSON output
- Survival Scenario Training Engine — interactive branching simulation, AI-generated content
- DS Email Formatting Engine — post-processes AI copy for Flodesk-compliant output
- Defend Survive Prepare — full DTC eCommerce + affiliate, $22K/mo, 280K list
- ModernCombatAndSurvival.com — subscription continuity platform, 100K+ audience, 6-fig recurring
- Aussie Labs WordPress Emergency Recovery — total site failure → full recovery in <24hr, zero data loss

EDUCATION
- University of Texas at Austin — Psychology / Sociology / Business Administration (122 credit hours, PHP/MySQL emphasis)
- FINRA Series 7, 55, 63
- LinkedIn Assessed: PHP, JavaScript, HTML, CSS, SEO, IT Operations, Agile, Business Analysis, Project Management
TXT;

    $stmt = $pdo->prepare("INSERT INTO agent_sources (agent_id, label, content, priority, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$agentId, 'Resume Snapshot (starter)', $starter, 50, 'Seeded by install.php. Edit, replace, or add additional sources via admin.']);
    $messages[] = "Seeded starter source for resume agent";
} else {
    $messages[] = "Agent 'resume' already exists — skipped seed";
}

?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><title>Install complete</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #080808; color: #eeebe5; padding: 40px; max-width: 720px; margin: 0 auto; line-height: 1.6; }
  h1 { color: #e8a020; }
  ul { background: #101010; border: 1px solid #222; padding: 20px 20px 20px 40px; border-radius: 4px; }
  li { margin: 4px 0; }
  a { color: #e8a020; }
  .warn { background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); color: #f43f5e; padding: 12px 16px; border-radius: 4px; margin-top: 24px; }
</style></head>
<body>
<h1>Install complete</h1>
<ul>
<?php foreach ($messages as $m) echo "<li>" . h($m) . "</li>\n"; ?>
</ul>
<p>Next:</p>
<ul>
  <li>Open <a href="admin.php">admin.php</a> to manage agents and sources.</li>
  <li>Embed snippet for the resume agent is shown inside the admin.</li>
</ul>
<div class="warn">Delete <code>install.php</code> from the server now that setup is done.</div>
</body></html>
