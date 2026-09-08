describe('Project autocomplete and explicit lookup', () => {
  it('does not import projects on its own', () => {
    // A real Drupal.org project that a fresh install does not know about
    // returns an empty list: the autocomplete never imports on its own.
    // (Assumes a freshly installed site, which CI provides.)
    cy.request('/simplytest/projects/autocomplete?string=honeypot').should(
      (response) => {
        expect(response.status).to.eq(200);
        expect(response.body).to.eql([]);
      },
    );
  });

  it('imports a project through the explicit lookup endpoint', () => {
    const lookups = [
      {
        name: 'Pathauto',
        result: {
          title: 'Pathauto',
          shortname: 'pathauto',
          type: 'Module',
        },
      },
      {
        name: 'Password Policy',
        result: {
          title: 'Password Policy',
          shortname: 'password_policy',
          type: 'Module',
        },
      },
      {
        name: 'token',
        result: {
          title: 'Token',
          shortname: 'token',
          type: 'Module',
        },
      },
    ];
    lookups.forEach((example) => {
      cy.request('POST', '/simplytest/projects/lookup', {
        name: example.name,
      }).should((response) => {
        expect(response.status).to.eq(200);
        expect(response.body).to.eql(example.result);
      });
    });

    // Once imported, the autocomplete finds them locally.
    cy.request('/simplytest/projects/autocomplete?string=Password Pol').should(
      (response) => {
        expect(response.status).to.eq(200);
        expect(response.body[0].shortname).to.eql('password_policy');
      },
    );
  });

  it('rejects lookups for names that are not projects', () => {
    cy.request({
      method: 'POST',
      url: '/simplytest/projects/lookup',
      body: { name: 'not/a/project!' },
      failOnStatusCode: false,
    })
      .its('status')
      .should('eq', 400);
  });
});

describe('Look up on drupal.org button', () => {
  it('imports the typed project and selects it', () => {
    cy.intercept('POST', '**/simplytest/projects/lookup').as('lookup');
    cy.visit('/');
    // A real project a fresh install does not know about.
    cy.getByLabel('Module, theme or distribution').type('metatag');
    cy.contains('button', 'Look up “metatag” on drupal.org').click();
    cy.wait('@lookup').its('request.body').should('deep.equal', {
      name: 'metatag',
    });
    cy.getByLabel('Module, theme or distribution').should(
      'have.value',
      'Metatag',
    );
    cy.getByLabel('Version').should('exist');
  });

  // The search matches substrings, so an unknown project whose name is
  // contained in another project's name returned that neighbour and nothing
  // else. A non-empty list used to suppress the lookup, leaving no way to
  // reach the project actually asked for. Reported for "fox", which found
  // foxycart and spreadfirefox but offered no way to reach fox.
  it('offers the lookup when no result matches exactly', () => {
    cy.request('POST', '/simplytest/projects/lookup', { name: 'pathauto' });
    cy.visit('/');
    cy.getByLabel('Module, theme or distribution').type('path');

    // The substring match is listed, and the lookup is still offered.
    cy.contains('[role="option"]', 'Pathauto').should('be.visible');
    cy.contains('button', 'Look up “path” on drupal.org').should('be.visible');
  });

  it('drops the lookup once a result matches exactly', () => {
    cy.request('POST', '/simplytest/projects/lookup', { name: 'pathauto' });
    cy.visit('/');
    cy.getByLabel('Module, theme or distribution').type('pathauto');

    cy.contains('[role="option"]', 'Pathauto').should('be.visible');
    cy.contains('button', 'Look up').should('not.exist');
  });

  it('treats a typed title as the shortname it normalizes to', () => {
    cy.request('POST', '/simplytest/projects/lookup', {
      name: 'password_policy',
    });
    cy.visit('/');
    // Spaces normalize to underscores the way searchFromProjects() does, so
    // this is an exact match on password_policy and needs no lookup.
    cy.getByLabel('Module, theme or distribution').type('password policy');

    cy.contains('[role="option"]', 'Password Policy').should('be.visible');
    cy.contains('button', 'Look up').should('not.exist');
  });
});
