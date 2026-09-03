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
