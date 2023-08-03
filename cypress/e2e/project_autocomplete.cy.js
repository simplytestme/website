describe('Project autocomplete imports missing projects', () => {
  beforeEach(() => {
    cy.installDrupal();
  })
  const autocompleteQueries = [
    {
      'query': 'Pathauto',
      'result': {
        'sandbox' : false,
        'shortname' : 'pathauto',
        'title' : 'Pathauto',
        'type' : 'Module',
      }
    },
    {
      'query': 'Password Policy',
      'result': {
        'sandbox' : false,
        'shortname' : 'password_policy',
        'title' : 'Password Policy',
        'type' : 'Module',
      }
    },
    {
      'query': 'token',
      'result': {
        'sandbox' : false,
        'shortname' : 'token',
        'title' : 'Token',
        'type' : 'Module',
      }
    },
    {
      'query': 'Bootstrap',
      'result': {
        'sandbox' : false,
        'shortname' : 'bootstrap',
        'title' : 'Bootstrap',
        'type' : 'Theme',
      }
    },
    {
      'query': 'Password Pol',
      'result': {
        'sandbox' : false,
        'shortname' : 'password_policy',
        'title' : 'Password Policy',
        'type' : 'Module',
      }
    },
  ]

  it('autocomplete imports projects dynamically ', () => {
    autocompleteQueries.forEach((example) => {
      cy.request('/simplytest/projects/autocomplete?string=' + example.query)
        .should(response => {
          expect(response.status).to.eq(200)
          expect(response.body[0]).to.deep.equal(example.result)
        })
    })
  })

})
