describe('Autofil of launch form from query parameters', function () {
  it('should prefill the form from a given user input', function () {
    cy.visit('/project/drupal/9.3.x', {
      qs: {
        'patch': [
          'https://www.drupal.org/files/issues/2021-05-16/3214191-2.patch',
        ]
      }
    })
    cy.location('pathname').should('contain', '/configure')
    cy.location('search').should('contain', 'project=drupal')
    cy.location('search').should('contain', 'version=9.3.x')
    cy.location('search').should('contain', 'patch=https%3A//www.drupal.org/files/issues/2021-05-16/3214191-2.patch')
    cy.getByLabel('Evaluate Drupal projects')
      .should('have.value', 'Drupal core')
    cy.getByLabel('Project version')
      .should('have.value', '9.3.x-dev')
    cy.getByLabel('Project patch 0')
      .should('have.value', 'https://www.drupal.org/files/issues/2021-05-16/3214191-2.patch')
  })
})
