describe('Project autocomplete imports missing projects', () => {
  const autocompleteQueries = [
    {
      'query': 'Pathauto',
      'result': {
        'title' : 'Pathauto',
        'shortname' : 'pathauto',
        'type' : 'Module',
      }
    },
    {
      'query': 'Password Policy',
      'result': {
        'title' : 'Password Policy',
        'shortname' : 'password_policy',
        'type' : 'Module',
      }
    },
    {
      'query': 'token',
      'result': {
        'title' : 'Token',
        'shortname' : 'token',
        'type' : 'Module',
      }
    },
    {
      'query': 'Bootstrap',
      'result': {
        'title' : 'Bootstrap',
        'shortname' : 'bootstrap',
        'type' : 'Theme',
      }
    },
    {
      'query': 'Password Pol',
      'result': {
        'title' : 'Password Policy',
        'shortname' : 'password_policy',
        'type' : 'Module',
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
