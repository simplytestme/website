// The progress page decides on its own whether a finished build is worth
// redirecting into. composer-patches aborts the build when a patch is refused,
// so a refused patch normally arrives as a failed job — but that is a property
// of the pinned major, and the pin has drifted out from under us once already.
// These cover the guard that keeps a silently unpatched sandbox from looking
// like a success.
//
// @see https://www.drupal.org/project/simplytest/issues/3388692
const PROGRESS_PATH = '/tugboat/progress/instance-1/job-1';
const PATCH_URL =
  'https://git.drupalcode.org/issue/drupal-3273986/-/commit/7bd5d37bb6926152fd9cca21cc565824e6b034f2';

// Enough of the Tugboat state payload for the page to render a finished build.
function readyPreview(logMessages) {
  return {
    type: 'preview',
    state: 'ready',
    url: '/user/login',
    progress: 100,
    createdAt: '2026-01-01T00:00:00.000Z',
    updatedAt: '2026-01-01T00:01:00.000Z',
    logs: logMessages.map((message, id) => ({ id, message })),
  };
}

const CLEAN_LOG = [
  'SIMPLYEST_STAGE_DOWNLOAD',
  'SIMPLYEST_STAGE_PATCHING',
  'SIMPLYEST_STAGE_INSTALLING',
  'instance (simplytest) is ready',
];

const REFUSED_PATCH_LOG = [
  ...CLEAN_LOG.slice(0, 2),
  `  No available patcher was able to apply patch ${PATCH_URL}`,
  ...CLEAN_LOG.slice(2),
];

describe('Progress page handling of a refused patch', function () {
  it('does not redirect into a sandbox that is missing its patch', function () {
    cy.intercept('GET', '/tugboat/status/**', readyPreview(REFUSED_PATCH_LOG));
    // The redirect is a timer, so drive it rather than waiting it out.
    cy.clock();
    cy.visit(PROGRESS_PATH, {
      qs: { project: 'drupal', version: '11.4.6', patch: PATCH_URL },
    });

    cy.contains('Your sandbox is ready, but the patch is not in it').should(
      'be.visible',
    );
    cy.contains('Built without the patch').should('be.visible');

    // The log carries the only explanation, so it opens without being asked.
    cy.contains('No available patcher was able to apply patch').should(
      'be.visible',
    );

    // The sandbox does exist, so both ways forward stay on offer.
    cy.contains('a', 'Open sandbox').should('have.attr', 'href', '/user/login');
    cy.contains('a', 'Fix the patch and rebuild').should(
      'have.attr',
      'href',
      `/?project=drupal&version=11.4.6&patch=${encodeURIComponent(PATCH_URL)}`,
    );

    // Well past the redirect delay, the page is still here.
    cy.tick(10000);
    cy.location('pathname').should('eq', PROGRESS_PATH);
  });

  it('still redirects when every patch applied', function () {
    cy.intercept('GET', '/tugboat/status/**', readyPreview(CLEAN_LOG));
    cy.clock();
    cy.visit(PROGRESS_PATH, {
      qs: { project: 'drupal', version: '11.4.6', patch: PATCH_URL },
    });

    cy.contains('Your sandbox is ready').should('be.visible');
    cy.contains('Opening it in a moment').should('be.visible');

    cy.tick(10000);
    cy.location('pathname', { timeout: 10000 }).should('eq', '/user/login');
  });
});
