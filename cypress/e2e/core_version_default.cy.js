// The Drupal core select defaults to the newest release the chosen project is
// compatible with. Core publishes alphas for the next major long before it is
// released, and a project whose core_version_requirement does not close at the
// current major is compatible with those, so the newest release is a
// pre-release for months at a time. Defaulting to one puts people on an
// unreleased core they did not ask for, and on a major with no base preview,
// so the sandbox builds from scratch as well.
describe('The Drupal core version the form defaults to', function () {
  beforeEach(() => {
    cy.intercept('GET', '**/simplytest/projects/autocomplete**', (req) => {
      if (req.query.string === 'Pathauto') {
        req.reply({ fixture: 'launch_form/autocomplete_pathauto.json' });
      }
    });
    cy.intercept('GET', '**/simplytest/project/pathauto/versions', {
      fixture: 'launch_form/project_versions_pathauto.json',
    });
    cy.intercept('GET', '**/one-click-demos', {
      fixture: 'launch_form/one_click_demos.json',
    });
  });

  it('skips a pre-release at the top of the list', () => {
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.14', {
      fixture: 'launch_form/core_compat_with_prerelease.json',
    });
    cy.visit('/');
    cy.pickProject('Pathauto');
    cy.toggleAdvancedOptions();

    cy.getByLabel('Drupal core').should('have.value', '11.4.7');
    // Still offered, just not chosen for them.
    cy.getByLabel('Drupal core')
      .find('option[value="12.0.0-alpha1"]')
      .should('exist');
  });

  it('keeps a pre-release when the project has nothing else', () => {
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.14', {
      fixture: 'launch_form/core_compat_only_prereleases.json',
    });
    cy.visit('/');
    cy.pickProject('Pathauto');
    cy.toggleAdvancedOptions();

    cy.getByLabel('Drupal core').should('have.value', '12.0.0-alpha1');
  });
});
