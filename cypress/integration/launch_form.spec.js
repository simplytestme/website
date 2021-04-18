describe('Test the launch form', function () {
  beforeEach(() => {
    // cy.installDrupal();
  })
  it('allows autocompleting of a project with a version selected', () => {
    cy.visit('/')
    cy.getByLabel('Evaluate Drupal projects')
      .type('Password Policy')
      .wait(300)
      .wait(200)
      .type('{downarrow}{enter}')
    cy.getByLabel('Project version')
      .should('have.value', '7.x-1.0')
  })
  it('with a project and modify the drupal core version', () => {
    cy.visit('/')
    cy.getByLabel('Evaluate Drupal projects')
      .type('Password Policy')
      .wait(300)
      .wait(200)
      .type('{downarrow}{enter}')
    cy.getByLabel('Project version')
      .should('have.value', '7.x-1.0')
    cy.toggleDetailsElement('Advanced options')
    cy.getByLabel('Drupal core version')
      .select('7.79')
  })
})
