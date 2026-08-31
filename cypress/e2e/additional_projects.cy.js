describe('Tests additional projects and version constraints', () => {
  before(() => {
    // The autocomplete no longer imports projects on its own.
    ['password_policy', 'password_policy_pwned'].forEach((name) => {
      cy.request('POST', '/simplytest/projects/lookup', { name })
    })
  })
  beforeEach(() => {
    cy.visit('/')
  })
  it('should restrict incompatible additional project releases', function () {
    cy.pickProject('Password Policy')
    cy.getByLabel('Version')
      .should('have.value', '4.0.3')
    cy.toggleAdvancedOptions()
    cy.get('button').contains('Add another project').click();
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
