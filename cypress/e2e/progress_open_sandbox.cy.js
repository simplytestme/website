// How the progress page hands over a finished sandbox.
//
// It never redirects. The sandbox opens in a new tab, so the progress page and
// its build log stay where they were. The first open goes through the one-time
// login link the build printed, so people land signed in; after that the link
// is spent and the button falls back to the plain URL.
//
// @see https://git.drupalcode.org/project/simplytest/-/work_items/3529448
// @see https://git.drupalcode.org/project/simplytest/-/work_items/3423289
const PROGRESS_PATH = '/tugboat/progress/instance-1/job-1';

const READY = {
  type: 'preview',
  state: 'ready',
  url: '/user/login',
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

describe('Progress page sandbox link', function () {
  it('opens the sandbox in a new tab, signed in once', function () {
    cy.intercept('GET', '/tugboat/status/**', READY).as('status');
    cy.clock();
    cy.visit(PROGRESS_PATH, { qs: { project: 'drupal', version: '11.4.6' } });
    cy.wait('@status');

    cy.contains('Your sandbox is ready').should('be.visible');
    cy.contains('signed in as an administrator').should('be.visible');
    cy.contains('a', 'Open sandbox')
      .should('have.attr', 'target', '_blank')
      .and('have.attr', 'href', '/user/password');

    // Cypress drives one tab, so keep the click from opening another. React
    // still sees the click.
    cy.contains('a', 'Open sandbox')
      .then(($link) => $link.on('click', (e) => e.preventDefault()))
      .click();
    cy.contains('a', 'Open sandbox').should('have.attr', 'href', '/user/login');
    cy.contains('use admin as both the username and the password').should(
      'be.visible',
    );

    // A reload does not offer the spent link again.
    cy.reload();
    cy.wait('@status');
    cy.contains('a', 'Open sandbox').should('have.attr', 'href', '/user/login');

    cy.contains('button', 'View build log').click();
    cy.contains('instance (simplytest) is ready').should('be.visible');

    cy.tick(10000);
    cy.location('pathname').should('eq', PROGRESS_PATH);
  });
});
