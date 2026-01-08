describe('Test the launch form', function () {
  beforeEach(() => {
    // Mock autocomplete
    cy.intercept('GET', '**/simplytest/projects/autocomplete**', (req) => {
      if (req.query.string === 'Pathauto') {
        req.reply({ fixture: 'launch_form/autocomplete_pathauto.json' });
      } else if (req.query.string === 'Password Policy') {
        req.reply({ fixture: 'launch_form/autocomplete_password_policy.json' });
      }
    });

    // Mock project versions
    cy.intercept('GET', '**/simplytest/project/pathauto/versions', { fixture: 'launch_form/project_versions_pathauto.json' });
    cy.intercept('GET', '**/simplytest/project/password_policy/versions', { fixture: 'launch_form/project_versions_password_policy.json' });

    // Mock core compatibility
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.14', { fixture: 'launch_form/core_compat_pathauto_8.x-1.14.json' });
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.6', { fixture: 'launch_form/core_compat_pathauto_8.x-1.6.json' });
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.11', { fixture: 'launch_form/core_compat_pathauto_8.x-1.11.json' });
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/7.x-1.0', { fixture: 'launch_form/core_compat_pathauto_7.x-1.0.json' });
    cy.intercept('GET', '**/simplytest/core/compatible/password_policy/4.0.3', { fixture: 'launch_form/core_compat_password_policy_4.0.3.json' });

    // Mock One Click Demos
    cy.intercept('GET', '**/one-click-demos', { fixture: 'launch_form/one_click_demos.json' });

    cy.visit('/')
  })
  it('allows autocompleting of a project with a version selected', () => {
    cy.pickProject('Password Policy')
    cy.getByLabel('Project version')
      .should('have.value', '4.0.3')
  })
  it('with a project and modify the drupal core version', () => {
    cy.pickProject('Password Policy')
    cy.getByLabel('Project version')
      .should('have.value', '4.0.3')
    cy.toggleDetailsElement('Advanced options')
    cy.getByLabel('Drupal Core')
      .select('9.5.9')
  })
  it('should allow me to attach a patch to my project', function () {
    cy.pickProject('Pathauto')
    cy.toggleDetailsElement('Advanced options')
    cy.getByLabel('Project patch 0')
      .type('https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch')
    cy.wait(200);
    cy.get('button').contains('Add patch').click();
    cy.getByLabel('Project patch 1');
    cy.get('#project_patch_1').get('button').contains('×').click();
    cy.get('#project_patch_0').get('button').contains('×').click();
  })
  it('should adjust available core versions based on compatibility', function () {
    cy.pickProject('Pathauto')
    cy.getByLabel('Project version')
      .should('have.value', '8.x-1.14')
    cy.toggleDetailsElement('Advanced options')
    cy.fixture('launch_form/core_compat_pathauto_8.x-1.14.json').then((data) => {
      cy.getByLabel('Drupal Core')
        .should('have.value', data.list[0].version)
    })
    cy.getByLabel('Project version')
      .select('8.x-1.6')
    cy.getByLabel('Drupal Core')
      .should('have.value', '8.9.19')
    cy.getByLabel('Project version')
      .select('8.x-1.11')
    cy.getByLabel('Drupal Core')
      .select('9.5.0')
  })
  it('should show the Umami demo for Drupal 8.6.x and Drupal 9 sites', function () {
    cy.pickProject('Pathauto')
    cy.getByLabel('Project version')
      .should('have.value', '8.x-1.14')
    cy.getByLabel('Project version')
      .select('7.x-1.0')
    cy.toggleDetailsElement('Advanced options')

    // Drupal 7 has no Umami.
    cy.getByLabel('Drupal Core')
      .should('have.value', '7.103')
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')

    // Default Drupal 8 has Umami.
    cy.getByLabel('Project version')
      .select('8.x-1.6')
    cy.getByLabel('Drupal Core')
      .should('have.value', '8.9.19')
    cy.wait(100);
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')
    cy.getByLabel('Install profile')
      .contains('Umami Demo')

    // Drupal < 8.6 doesn't have Umami
    cy.getByLabel('Drupal Core')
      .select('8.5.9')
    cy.wait(100);
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')
    cy.getByLabel('Install profile')
      .contains('Umami Demo').should('not.exist')

    // Drupal 9 has Umami.
    cy.getByLabel('Project version')
      .select('8.x-1.11')
    cy.getByLabel('Drupal Core')
      .select('9.5.0')
    cy.wait(100);
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')
    cy.getByLabel('Install profile')
      .contains('Umami Demo')
  })
})
