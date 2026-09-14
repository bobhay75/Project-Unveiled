# $35 Project Unveiled Revenue Test

## Objective

Validate whether cold Meta traffic will buy the $7 Project Unveiled digital edition. This is a seven-day learning test, not proof of scalable profit.

## Campaign configuration

- Platform: Meta Ads Manager
- Budget: $5/day for 7 days; $35 lifetime maximum
- Objective: Traffic
- Performance goal: Maximize landing-page views
- Destination: `https://bobsome1.com/store/?utm_source=facebook&utm_medium=paid_social&utm_campaign=pu_7_dollar_test_2026_09&utm_content=truth_questions_v1#books`
- Audience controls: United States, age 30+
- Audience approach: broad. Let the creative identify interested readers. Do not use or imply sensitive religious-belief targeting. If the current Ads Manager flow uses Advantage+ audience, treat any available interests only as suggestions rather than guaranteed limits.
- Placements: Advantage+ placements
- Creative: one 4:5 static image or 9:16 short video; no split test at this budget
- Frequency: stop early if frequency exceeds 2.5 with no checkout clicks

This release has owned first-party analytics, not a Meta Pixel or Conversions API purchase event. Do not select Sales or claim Meta purchase optimization for this test. A future Meta conversion integration requires a separate privacy and data-sharing decision.

## Primary copy

**Text:**

What if honest questions are not the enemy of faith?

Project Unveiled follows the record through Scripture, history, empire, doctrine and fruit—without asking you to abandon Jesus or pretend the evidence says more than it does.

Read the complete public edition free. Get the downloadable digital edition for $7 if you want to keep it and support the work.

Truth is not afraid of questions.

**Headline:** Question what you inherited. Examine it yourself.

**Description:** Complete digital edition · $7 · Free public reader available

**CTA:** Learn More

## Required preflight

1. Confirm the exact approved repository commit is deployed and both `node tests/trust-worthy-lab/live-release-gate.mjs` and `node tests/revenue-funnel/live-preflight.mjs` pass from the repository root.
2. Open the destination on a phone and desktop; verify layout, copy, privacy link, free-reader link, and checkout handoff.
3. Replace the personal PayPal.Me path with a fixed-price PayPal Payment Link for the digital edition. Confirm the hosted page names Project Unveiled, shows exactly $7 USD, identifies the intended merchant, collects no shipping address, and treats the transaction as goods/services. Do not send ad traffic to a generic person-to-person payment page.
4. Complete one controlled purchase. Confirm settlement in PayPal, receipt delivery, the buyer-to-seller email handoff, and delivery of both `Project_Unveiled_Print_Ready_Interior_FINAL.pdf` and `Project_Unveiled_Kindle_FINAL.epub`. Refund the controlled purchase after verification.
5. Confirm `pageview`, `engaged_30s`, and `product_checkout_click` appear in the private owned-analytics dashboard. Confirm the checkout click is not displayed as a completed payment.
6. In Ads Manager, verify Traffic, landing-page-view optimization, one creative, the exact UTM destination, and a $35 lifetime cap with no automatic budget increase.
7. Only then activate the ad.

## Platform guardrails checked September 14, 2026

- Meta removed detailed-targeting options tied to sensitive topics such as religious beliefs. Source: https://www.facebook.com/business/news/removing-certain-ad-targeting-options-and-expanding-our-ad-controls
- Meta's current Advantage+ audience controls and suggestions do not guarantee that audience suggestions remain hard limits. Source: https://www.facebook.com/business/help/938372127764391
- Meta describes the Pixel as the mechanism for measuring website actions in its ad system; this release intentionally uses owned analytics only. Source: https://www.facebook.com/business/help/742478679120153
- PayPal recommends fixed product/service Payment Links for a hosted checkout. Source: https://www.paypal.com/us/business/accept-payments/payment-links

## Scorecard

- Landing-page views
- 30-second engaged visits
- Product-checkout clicks
- Completed payments (confirmed manually)
- Cost per landing-page view
- Checkout-click rate
- Cost per completed payment

At $7 with manual fulfillment, this test is successful as a signal if it produces at least one verified purchase or at least three checkout clicks from 50+ landing-page views. Do not scale on clicks alone. Pause after $35 and inspect the complete funnel before changing creative, audience or budget.
