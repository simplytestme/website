// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

// Copied from https://glebbahmutov.com/cypress-examples/6.8.0/recipes/form-input-by-label.html#simple-custom-command
Cypress.Commands.add('getByLabel', (label) => {
  return cy.contains('label', label)
    .invoke('attr', 'for')
    .then((id) => cy.get('#' + id));
})
Cypress.Commands.add('toggleDetailsElement', (label) => {
  return cy.contains('summary', label).click()
})

Cypress.Commands.add('pickProject', input => {
  cy.getByLabel('Evaluate Drupal projects')
    .type(input);

  cy.get('[role="option"]')
    .contains(input)
    .should('be.visible')
    .click();
})
