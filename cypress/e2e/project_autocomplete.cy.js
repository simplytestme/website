describe('Project autocomplete imports missing projects', () => {
  const autocompleteQueries = [
    {
      'query': 'Pathauto',
      'result': {
        'title' : 'Pathauto',
        'shortname' : 'pathauto',
        'sandbox' : false,
        'type' : 'Module',
      }
    },
    {
      'query': 'Password Policy',
      'result': {
        'title' : 'Password Policy',
        'shortname' : 'password_policy',
        'type' : 'Module',
        'sandbox' : false,
      }
    },
    {
      'query': 'token',
      'result': {
        'title' : 'Token',
        'shortname' : 'token',
        'sandbox' : false,
        'type' : 'Module',
      }
    },
    {
      'query': 'Bootstrap',
      'result': {
        'title' : 'Bootstrap',
        'shortname' : 'bootstrap',
        'sandbox' : false,
        'type' : 'Theme',
      }
    },
    {
      'query': 'Password Pol',
      'result': {
        'title' : 'Password Policy',
        'shortname' : 'password_policy',
        'type' : 'Module',
        'sandbox' : 0,
      }
    },
  ]

  it('autocomplete imports projects dynamically ', () => {
    autocompleteQueries.forEach((example) => {
      cy.request('/simplytest/projects/autocomplete?string=' + example.query)
        .should(response => {
          expect(response.status).to.eq(200)
          expect(response.body[0]).to.eql(example.result)
        })
    })
  })

})
