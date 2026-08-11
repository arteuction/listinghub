import { AxeBuilder } from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';

/**
 * WCAG 2.1 A/AA, failing only on `serious` and `critical`.
 *
 * Moderate/minor findings are real but are frequently contextual (contrast on
 * a decorative element, a landmark preference); gating on them would make the
 * suite noisy enough to be ignored, which is worse than a narrower gate that
 * people trust. The severity floor is a deliberate line, not an oversight.
 */
export async function expectNoSeriousA11yViolations(page: Page, context?: string) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    const blocking = results.violations.filter(
        (v) => v.impact === 'serious' || v.impact === 'critical',
    );

    // Name the rule and the offending element, so a failure is actionable
    // straight from the CI log instead of needing a local repro.
    const detail = blocking
        .map((v) => `${v.id} (${v.impact}): ${v.help}\n    ${v.nodes.map((n) => n.target.join(' ')).join('\n    ')}`)
        .join('\n\n');

    expect(blocking, `${context ?? page.url()} has accessibility violations:\n\n${detail}`).toEqual([]);
}
