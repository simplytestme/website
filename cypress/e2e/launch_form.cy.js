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
    cy.intercept('GET', '**/simplytest/project/pathauto/versions', {
      fixture: 'launch_form/project_versions_pathauto.json',
    });
    cy.intercept('GET', '**/simplytest/project/password_policy/versions', {
      fixture: 'launch_form/project_versions_password_policy.json',
    });

    // Mock core compatibility
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.14', {
      fixture: 'launch_form/core_compat_pathauto_8.x-1.14.json',
    });
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.6', {
      fixture: 'launch_form/core_compat_pathauto_8.x-1.6.json',
    });
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/8.x-1.11', {
      fixture: 'launch_form/core_compat_pathauto_8.x-1.11.json',
    });
    cy.intercept('GET', '**/simplytest/core/compatible/pathauto/7.x-1.0', {
      fixture: 'launch_form/core_compat_pathauto_7.x-1.0.json',
    });
    cy.intercept('GET', '**/simplytest/core/compatible/password_policy/4.0.3', {
      fixture: 'launch_form/core_compat_password_policy_4.0.3.json',
    });

    // Mock One Click Demos
    cy.intercept('GET', '**/one-click-demos', {
      fixture: 'launch_form/one_click_demos.json',
    });

    cy.visit('/');
  });

  it('allows autocompleting of a project with a version selected', () => {
    cy.pickProject('Password Policy');
    cy.getByLabel('Version').should('have.value', '4.0.3');
  });

  it('with a project and modify the drupal core version', () => {
    cy.pickProject('Password Policy');
    cy.getByLabel('Version').should('have.value', '4.0.3');
    cy.toggleAdvancedOptions();
    cy.getByLabel('Drupal core').select('9.5.9');
  });

  it('should allow me to attach a patch to my project', function () {
    cy.pickProject('Pathauto');
    cy.toggleAdvancedOptions();
    cy.getByLabel('Project patch 1').type(
      'https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch',
    );
    cy.get('button').contains('Add another patch').click();
    cy.get('input[type=url]').should('have.length', 2);
    // Select the remove buttons by accessible name. `cy.get()` always queries
    // from the document root, so the old `cy.get('#project_patch_1').get(...)`
    // was not scoped to that row and clicked the first × on the page twice.
    cy.get('button[aria-label="Remove patch 2"]').click();
    cy.get('button[aria-label="Remove patch 1"]').click();
    cy.get('input[type=url]').should('have.length', 1);
  });
  // The test above types into the first field before adding a second, so it
  // never covered adding one straight from the placeholder row. That row is
  // derived rather than stored, which is the case most likely to break.
  it('should add a second patch field before anything is typed', function () {
    cy.pickProject('Pathauto');
    cy.toggleAdvancedOptions();
    cy.get('input[type=url]').should('have.length', 1);
    cy.get('button').contains('Add another patch').click();
    cy.getByLabel('Project patch 2');
    cy.get('input[type=url]').should('have.length', 2);
  });
  // #3494635: the placeholder was the only hint about the expected format, and
  // it disappears the moment you type. The description below the field does not.
  it('should keep the patch format hint visible while typing', function () {
    cy.pickProject('Pathauto');
    cy.toggleAdvancedOptions();
    cy.getByLabel('Project patch 1').type(
      'https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch',
    );

    // Read the id off the field rather than hardcoding the hint's id here.
    cy.getByLabel('Project patch 1')
      .invoke('attr', 'aria-describedby')
      .then((hintId) => {
        expect(hintId, 'the patch field describes itself').to.be.a('string');
        cy.get(`[id="${hintId}"]`)
          .should('be.visible')
          .and('contain', 'https://www.drupal.org/files/');
      });
  });

  it('should adjust available core versions based on compatibility', function () {
    cy.pickProject('Pathauto');
    cy.getByLabel('Version').should('have.value', '8.x-1.14');
    cy.toggleAdvancedOptions();
    cy.fixture('launch_form/core_compat_pathauto_8.x-1.14.json').then(
      (data) => {
        cy.getByLabel('Drupal core').should('have.value', data.list[0].version);
      },
    );
    cy.getByLabel('Version').select('8.x-1.6');
    cy.getByLabel('Drupal core').should('have.value', '8.9.20');
    cy.getByLabel('Version').select('8.x-1.11');
    cy.getByLabel('Drupal core').select('9.5.0');
  });

  it('should show the Umami demo for Drupal 8.6.x and Drupal 9 sites', function () {
    cy.pickProject('Pathauto');
    cy.getByLabel('Version').should('have.value', '8.x-1.14');
    cy.getByLabel('Version').select('7.x-1.0');
    cy.toggleAdvancedOptions();

    // Drupal 7 has no Umami.
    cy.getByLabel('Drupal core').should('have.value', '7.103');
    cy.getByLabel('Install profile').contains('Minimal');
    cy.getByLabel('Install profile').contains('Standard');

    // Default Drupal 8 has Umami.
    cy.getByLabel('Version').select('8.x-1.6');
    cy.getByLabel('Drupal core').should('have.value', '8.9.20');
    cy.getByLabel('Install profile').contains('Minimal');
    cy.getByLabel('Install profile').contains('Standard');
    cy.getByLabel('Install profile').contains('Umami Demo');

    // Drupal < 8.6 doesn't have Umami
    cy.getByLabel('Drupal core').select('8.5.9');
    cy.getByLabel('Install profile').contains('Minimal');
    cy.getByLabel('Install profile').contains('Standard');
    cy.getByLabel('Install profile').contains('Umami Demo').should('not.exist');

    // Drupal 9 has Umami.
    cy.getByLabel('Version').select('8.x-1.11');
    cy.getByLabel('Drupal core').select('9.5.0');
    cy.getByLabel('Install profile').contains('Minimal');
    cy.getByLabel('Install profile').contains('Standard');
    cy.getByLabel('Install profile').contains('Umami Demo');
  });
});
