describe('Tests additional projects and version constraints', () => {
  beforeEach(() => {
    cy.visit('/')
  })
  it('should restrict incompatible additional project releases', function () {
    cy.pickProject('Password Policy')
    cy.getByLabel('Project version')
      .should('have.value', '4.0.3')
    cy.toggleDetailsElement('Advanced options')
    cy.get('button').contains('Add additional project').click();
    cy.get('#additional_project_0').getByLabel('Additional project name')
      .type('Password Policy')
      .wait(100)
      .type (' Pwned')
      .wait(2000)
      .type('{downArrow}{enter}')

    cy.wait(400)
    cy.get('#additional_project_0 select')
      .should('have.value', '2.0.1')
  })
})
