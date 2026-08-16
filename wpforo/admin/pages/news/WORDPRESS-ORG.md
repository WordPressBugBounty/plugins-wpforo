# wordpress.org Review Notes — gVectors News Module

Draft email to plugins@wordpress.org describing the architecture BEFORE the SVN push
that first ships this module inside wpForo. Send from the plugin owner's account.

---

**Subject:** wpForo — heads-up on new opt-in update/news notification service before our next release

Hi plugins team,

Before pushing the next wpForo release we'd like to describe a new optional feature so
your review has full context (Guidelines 7 and 11 in particular).

**What it does**
wpForo (and later other gVectors plugins) will include a small shared library that can
show addon-related news as dismissible admin notices and send two kinds of email to
site administrators via the site's own wp_mail: a news digest, and reminders before a
purchased addon license expires without renewal.

**Consent (Guideline 7)**
- The service is OFF by default. Zero outbound requests are made until an administrator
  with `activate_plugins` explicitly clicks Enable on a one-time notice (or on the
  settings page). The notice states exactly what is sent and links to our privacy policy.
- Admins can opt out again at any time from the settings page (wpForo → News & Emails);
  disabling stops the daily cron and all requests immediately.

**Data sent (only after opt-in, once per day)**
- Site address and an anonymous site identifier (an HMAC-SHA256 hash derived from the
  site domain and the site's own WordPress salts)
- Slugs and installed versions of gVectors addons on the site
- No personal data, no email addresses, no post/user content — ever.
- Endpoints: `https://store.gvectors.com/news` and `https://store.gvectors.com/at-risk-licenses`.
- Full disclosure is included in readme.txt under "Use of 3rd Party Services".

**Notices (Guideline 11)**
- Dismissible, per-user dismissal, hard cap of 3 at once, and rendered only on
  Dashboard Home, Updates, Plugins pages and the plugin's own admin pages.

**Emails**
- Sent by the site itself (wp_mail), only to administrators, each with an
  unsubscribe/preferences link pointing to a settings page where every admin can opt
  out individually or by category. Site-wide switches exist too. Delivery state is
  tracked so nothing is ever sent twice.

**Uninstall**
- All options, transients and user meta created by the module are removed on uninstall.

Happy to answer any questions or adjust anything you flag.

Thanks,
gVectors Team

---

## Pre-release checklist (module side)

- [ ] `GVECTORS_PROXY_URL` default in `modules/news/bootstrap.php` + `modules/license/bootstrap.php`
      switched from `https://gv.loc` to `https://store.gvectors.com`
- [ ] Proxy production `.env`: strong `ADMIN_PASS_HASH`, real `SPENDING_WEBHOOK_SECRET`,
      `ALLOW_DEV_DOMAINS=0`, `TRUST_PROXY` matching topology
- [ ] readme.txt "Use of 3rd Party Services" section up to date (done)
- [ ] This email sent and acknowledged before the SVN push
