describe('Tests additional projects and version constraints', () => {
  beforeEach(() => {
    cy.visit('/')
  })
  it('should restrict incompatible additional project releases', function () {
    cy.pickProject('Password Policy')
    cy.getByLabel('Project version')
      .should('have.value', '7.x-1.0')
    cy.toggleDetailsElement('Advanced options')
    cy.get('button').contains('Add additional project').click();
    cy.get('#additional_project_0').getByLabel('Additional project name')
      .type('Password Policy')
      .wait(300)
      .type (' Pwned')
      .wait(300)
      .type('{downarrow}{enter}')
    cy.get('#additional_project_0 select')
      .contains('option').should('have.length', 0);

    cy.getByLabel('Project version')
      .select('8.x-3.0-beta1')
    cy.wait(400)
    cy.get('#additional_project_0 select')
      .should('have.value', '8.x-1.0-beta2')
  })
})
