// When the progress page sends someone into their sandbox, and where to.
//
// It redirects only when it watched the build run. Coming back to a finished
// build, with the back button or a saved link, keeps the page so the build log
// can still be read. The redirect goes through the one-time login link the
// build printed, so people land signed in.
//
// @see https://git.drupalcode.org/project/simplytest/-/work_items/3529448
// @see https://git.drupalcode.org/project/simplytest/-/work_items/3423289
const PROGRESS_PATH = '/tugboat/progress/instance-1/job-1';

const BUILDING = { type: 'job', state: 'building', progress: 60, logs: [] };

const READY = {
  type: 'preview',
  state: 'ready',
  url: '/user/login',
  // Any page on this site stands in for the sandbox's login link.
  loginUrl: '/user/password',
  progress: 100,
  createdAt: '2026-01-01T00:00:00.000Z',
  updatedAt: '2026-01-01T00:01:00.000Z',
  logs: [
    { id: 0, message: 'SIMPLYEST_STAGE_DOWNLOAD' },
    { id: 1, message: 'SIMPLYEST_STAGE_INSTALLING' },
    { id: 2, message: 'instance (simplytest) is ready' },
  ],
};

describe('Progress page redirect', function () {
  it('signs people in once the build they watched finishes', function () {
    let polls = 0;
    cy.intercept('GET', '/tugboat/status/**', (req) => {
      polls += 1;
      req.reply(polls === 1 ? BUILDING : READY);
    }).as('status');
    cy.clock();
    cy.visit(PROGRESS_PATH, { qs: { project: 'drupal', version: '11.4.6' } });
    cy.wait('@status');
    cy.tick(3000);
    cy.wait('@status');

    cy.contains('Opening it in a moment').should('be.visible');
    cy.contains('signed in as an administrator').should('be.visible');
    cy.contains('a', 'Open sandbox').should(
      'have.attr',
      'href',
      '/user/password',
    );

    cy.tick(3000);
    cy.location('pathname').should('eq', '/user/password');
  });

  it('stays put when the build was already finished', function () {
    cy.intercept('GET', '/tugboat/status/**', READY).as('status');
    cy.clock();
    cy.visit(PROGRESS_PATH, { qs: { project: 'drupal', version: '11.4.6' } });
    cy.wait('@status');

    cy.contains('Your sandbox is ready').should('be.visible');
    cy.contains('read the build log below').should('be.visible');
    // The login link was most likely spent on the way in the first time.
    cy.contains('a', 'Open sandbox').should('have.attr', 'href', '/user/login');
    cy.contains('use admin as both the username and the password').should(
      'be.visible',
    );

    cy.contains('button', 'View build log').click();
    cy.contains('instance (simplytest) is ready').should('be.visible');

    cy.tick(10000);
    cy.location('pathname').should('eq', PROGRESS_PATH);
  });
});
