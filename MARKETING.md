# VibeCodePC — Post-Release Marketing Strategy

## Positioning

**Tagline:** "Plug in. Scan. Code. Ship."

**One-liner:** VibeCodePC is a Raspberry Pi 5 that comes ready to code with AI — scan the QR, connect your accounts, and deploy to the web in minutes.

**Target audiences (in priority order):**
1. **Indie hackers / solo devs** — want a dedicated, always-on dev box that isn't their laptop
2. **Hobbyist programmers** — learning to code, want a self-contained environment
3. **Vibe coders** — non-traditional developers using AI (Copilot, ChatGPT) to build projects
4. **Self-hosters** — people who want to run their own services but find Docker/VPS setup painful
5. **Educators / coding bootcamps** — need consistent, pre-configured dev environments for students

**Competitive positioning:**
- vs. **VPS (DigitalOcean, Hetzner)**: VibeCodePC is physical, yours, no monthly compute bill, runs at home
- vs. **GitHub Codespaces / Gitpod**: No usage limits, no cloud dependency, AI pre-configured
- vs. **Bare Raspberry Pi**: Pre-configured, wizard-guided, tunnel/deploy built in — 0 to productive in 5 min
- vs. **Old laptop as server**: Purpose-built, silent, low power (15W), always-on, with managed tunnels

---

## Launch Strategy

### Pre-Launch (4 weeks before)

#### Build the Waitlist
- [ ] Landing page at `vibecodepc.com` with email capture
- [ ] "Reserve your VibeCodePC" — $0 reservation, email notification on launch
- [ ] Waitlist counter visible ("327 developers waiting")
- [ ] Early-bird pricing teaser ($249 vs $299)

#### Content Seeding
- [ ] **Demo video** (90 sec): unboxing → QR scan → wizard → deploy a site — all in one take
- [ ] **Twitter/X thread**: "I built a Raspberry Pi that sets up your entire dev environment in 5 minutes"
- [ ] **Reddit posts**: r/selfhosted, r/raspberry_pi, r/webdev, r/programming, r/homelab
- [ ] **Hacker News**: "Show HN: VibeCodePC — a Raspberry Pi 5 that's a complete AI coding station"
- [ ] **Dev.to / Hashnode article**: "Why I built a plug-and-code Raspberry Pi for AI-assisted development"

#### Community Setup
- [ ] Discord server with channels: #general, #setup-help, #show-your-projects, #feature-requests
- [ ] GitHub Discussions enabled on the repo (for open-source device app)

---

### Launch Week

#### Day 1: Announce
- [ ] Hacker News "Show HN" post (target morning US time, Tuesday–Thursday)
- [ ] Twitter/X announcement with demo video
- [ ] Reddit posts across target subreddits
- [ ] Email waitlist: "It's live. Early-bird pricing for 48 hours."
- [ ] Product Hunt launch (coordinate with PH community in advance)

#### Day 2–3: Ride the Wave
- [ ] Respond to every HN/Reddit comment personally
- [ ] Publish "How it works" technical deep-dive blog post
- [ ] Share user reactions / screenshots on Twitter
- [ ] YouTube creators: ship free units to 5–10 tech YouTubers (pre-arranged)

#### Day 4–7: Sustain
- [ ] Publish FAQ based on launch questions
- [ ] First "customer spotlight" if any early buyers share their setup
- [ ] Follow-up email to waitlist non-converters with FAQ + testimonials

---

## Ongoing Marketing Channels

### 1. Content Marketing (Weekly)

**Blog at `vibecodepc.com/blog`:**
- "Build and deploy a [X] with your VibeCodePC" tutorial series
  - Personal portfolio site (Astro)
  - SaaS MVP (Laravel)
  - Discord bot (Python)
  - AI chatbot (Next.js + Anthropic API)
  - Home automation dashboard (Livewire)
- "What's new" monthly update posts
- Guest posts from community members

**YouTube channel:**
- Setup walkthroughs
- "5-minute project" series — concept to deployed in 5 min
- Comparison videos (VibeCodePC vs Codespaces vs VPS)
- Community project showcases

### 2. Social Media (3–5x/week)

**Twitter/X strategy:**
- Build-in-public updates from the founder
- Retweet/quote user projects deployed on vibecodepc.com
- Engagement with #buildinpublic, #indiehackers, #raspberrypi communities
- Short video clips of the wizard and deployment flow

**Reddit strategy:**
- Genuine participation in r/selfhosted, r/raspberry_pi, r/homelab
- Monthly "What have you built?" threads in own subreddit r/vibecodepc
- Answer questions where VibeCodePC is a natural fit (not spammy)

### 3. SEO (Long-term)

**Target keywords:**
- "raspberry pi coding setup"
- "raspberry pi web server"
- "self-hosted development environment"
- "raspberry pi AI coding"
- "personal cloud server"
- "deploy website from raspberry pi"
- "copilot on raspberry pi"
- "raspberry pi vs vps"

**SEO content:**
- Comparison pages: "VibeCodePC vs [X]" for each competitor
- Tutorial-style landing pages for each use case
- "How to" articles targeting long-tail keywords

### 4. Community & Partnerships

**Discord community building:**
- Weekly "Show & Tell" voice chat
- Bot that showcases new projects deployed on vibecodepc.com
- Reward active helpers with free months of Pro tier

**Partnerships:**
- **Raspberry Pi Foundation**: Featured partner / case study
- **Anthropic / OpenAI**: Showcase integration, potential co-marketing
- **GitHub Education**: Student developer pack inclusion
- **Coding bootcamps**: Bulk pricing for classroom use (Team tier)
- **Tech YouTubers**: Ongoing sponsorship / review units

### 5. Referral Program

- Existing users get a referral link
- Referee gets $20 off hardware purchase
- Referrer gets 2 months free Pro subscription
- Top referrers: free hardware upgrades or exclusive merch

### 6. Developer Advocacy

- **Open source the device app** — builds trust, allows contributions
- Publish architecture decision records (ADRs)
- Speak at meetups / conferences (local dev meetups, Laracon, PiWars, Self-Hosted Summit)
- Sponsor relevant podcasts (Syntax.fm, Self-Hosted, Laravel News Podcast, Changelog)

---

## Paid Acquisition (Month 3+)

Start paid ads only after organic traction validates product-market fit.

### Google Ads
- Target keywords: "raspberry pi dev server", "self-hosted coding environment"
- Budget: $500/mo initially, scale with ROAS
- Landing pages per keyword cluster

### Twitter/X Ads
- Promote demo video to dev-adjacent audiences
- Target followers of: @github, @laaboratory, @tailaboratory, @anthropic
- Budget: $300/mo

### Reddit Ads
- Target r/selfhosted, r/raspberry_pi, r/webdev
- Use authentic ad format (looks like a post, not an ad)
- Budget: $200/mo

### YouTube Ads
- Pre-roll on tech/coding tutorials
- 15-sec version of demo video
- Budget: $400/mo

**Total paid budget (initial): ~$1,400/mo**
**Target CAC: <$50** (at $299 hardware + subscription LTV of ~$180/yr)

---

## Email Marketing

### Sequences

**Post-purchase:**
1. Day 0: Order confirmation + "What to expect" (shipping timeline)
2. Day 3: "Preparing your VibeCodePC" — what's being configured
3. Ship day: Tracking + "Quick Start Guide" PDF
4. Day 1 after delivery: "Scan the QR — let's set up!" with video link
5. Day 3 after delivery: "How's your setup going?" — link to support + Discord
6. Day 7: "Build your first project" — tutorial link
7. Day 14: "Share your creation" — encourage community sharing
8. Day 30: "Upgrade to Pro?" — highlight tunnel/subdomain features

**Nurture (non-buyers on waitlist):**
- Weekly digest: new projects deployed on VibeCodePC, tutorials, community highlights
- Monthly "State of VibeCodePC" — new features, metrics, roadmap updates
- Re-engagement: "We've added [feature] you asked about"

---

## Metrics & KPIs

### North Star Metric
**Active devices with at least 1 deployed project** (measures real value delivery)

### Funnel Metrics
| Stage            | Metric                        | Target (Month 3)  |
| ---------------- | ----------------------------- | ------------------ |
| Awareness        | Monthly site visitors          | 25,000             |
| Interest         | Waitlist / email signups       | 2,000              |
| Purchase         | Hardware units sold            | 200                |
| Activation       | Wizard completed               | 85% of buyers      |
| Retention        | Active after 30 days           | 70% of activated   |
| Revenue          | Subscription conversion        | 40% on Starter+    |
| Referral         | Referral rate                  | 15% of customers   |

### Unit Economics Target
| Metric            | Target          |
| ----------------- | --------------- |
| Hardware margin   | 50% ($150 COGS → $299 retail)  |
| Subscription ARPU | $8/mo blended   |
| LTV (2 years)     | $299 + $192 = $491 |
| CAC               | < $50           |
| LTV:CAC ratio     | > 9:1           |

---

## Launch Calendar (First 90 Days)

| Week | Activity                                                    |
| ---- | ----------------------------------------------------------- |
| -4   | Landing page live, waitlist open, pre-launch content        |
| -2   | Seed units to YouTubers + beta testers, Product Hunt scheduled |
| 0    | **LAUNCH**: HN, PH, Reddit, Twitter, email blast            |
| 1    | Respond to community, publish FAQ, first tutorials          |
| 2    | Customer spotlight, "Week 1 numbers" build-in-public post   |
| 4    | First monthly update, community showcase, referral program  |
| 6    | SEO content push (5 comparison/tutorial pages)              |
| 8    | Evaluate paid ads, first YouTube sponsor integration        |
| 10   | Second batch production based on demand                     |
| 12   | "90 days of VibeCodePC" retrospective, roadmap update       |

---

## Brand Guidelines (Brief)

- **Voice**: Technical but approachable. We're developers talking to developers. No corporate jargon.
- **Tone**: Excited but not hype-y. Confident but not arrogant.
- **Visual**: Dark theme primary (matches dev aesthetic), electric green accent (#00FF88), monospace type for headlines
- **Photography**: Real devices on real desks, not stock photos. Show the mess. Show the blinking LEDs.
- **Mascot**: Consider a pixel-art robot character for community/Discord identity

---

## Expansion Ideas (6–12 Months Post-Launch)

1. **VibeCodePC Pro** — Raspberry Pi CM5 + more RAM, faster SSD, fanless aluminum case ($499)
2. **VibeCodePC Classroom** — 10-pack for educators with central management dashboard
3. **Marketplace** — community-created project templates (revenue share)
4. **VibeCodePC Cloud** — same software, running on a VPS instead of a Pi (for users who want cloud)
5. **Plugin system** — community extensions for the dashboard (monitoring, databases, CMS)
6. **Mobile app** — manage your VibeCodePC from your phone (start/stop projects, view status)
