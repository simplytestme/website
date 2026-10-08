// A launch link can choose the Drupal core version and install profile, so an
// agent asked to reproduce a bug on Drupal 11.3 can send a link that does.
// `core` waits for the list of core releases the project supports, and falls
// back to the usual default when the project supports none that match.
describe('A launch link that sets core and profile', function () {
  beforeEach(() => {
    cy.intercept('GET', '**/simplytest/projects/autocomplete**', {
      fixture: 'launch_form/autocomplete_pathauto.json',
    });
    cy.intercept('GET', '**/simplytest/project/pathauto/versions', {
      fixture: 'launch_form/project_versions_pathauto.json',
    });
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.14', {
      fixture: 'launch_form/core_compat_with_prerelease.json',
    });
    cy.intercept('GET', '**/one-click-demos', {
      fixture: 'launch_form/one_click_demos.json',
    });
  });

  it('picks the newest stable release of the linked minor', () => {
    cy.visit('/', {
      qs: {
        project: 'pathauto',
        version: '8.x-1.14',
        core: '11.3',
        profile: 'minimal',
      },
    });

    cy.getByLabel('Drupal core').should('have.value', '11.3.0');
    cy.getByLabel('Install profile').should('have.value', 'minimal');
  });

  it('accepts an exact release', () => {
    cy.visit('/', {
      qs: { project: 'pathauto', version: '8.x-1.14', core: '11.4.6' },
    });

    cy.getByLabel('Drupal core').should('have.value', '11.4.6');
  });

  it('falls back to the defaults for values it cannot use', () => {
    cy.visit('/', {
      qs: {
        project: 'pathauto',
        version: '8.x-1.14',
        core: '9.x',
        profile: 'not_a_profile',
      },
    });

    cy.getByLabel('Drupal core').should('have.value', '11.4.7');
    cy.getByLabel('Install profile').should('have.value', 'standard');
  });
});
