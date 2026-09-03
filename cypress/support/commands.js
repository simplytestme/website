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
Cypress.Commands.add('getByLabel', (label, options = {}) => {
  return cy
    .contains('label', label, options)
    .invoke('attr', 'for')
    .then((id) => cy.get('#' + id, options));
});
Cypress.Commands.add('toggleAdvancedOptions', () => {
  return cy.contains('button', 'Advanced options').click();
});

// Picks a project in one additional project row. The row's version select
// only renders once the project's releases have loaded, so waiting for it is
// how a caller knows the pick has settled.
Cypress.Commands.add('pickAdditionalProject', (rowId, input) => {
  cy.intercept('GET', '**/simplytest/projects/autocomplete**').as(
    'autocomplete',
  );
  cy.get(`#${rowId}`).within(() => {
    cy.getByLabel('Additional project name').type(input);
    cy.wait('@autocomplete');
    cy.contains('[role="option"]', input).should('be.visible').click();
    cy.get('select').should('exist');
  });
});

Cypress.Commands.add('pickProject', (input) => {
  cy.intercept('GET', '**/simplytest/projects/autocomplete**').as(
    'autocomplete',
  );

  cy.getByLabel('Module, theme or distribution').type(input);
  cy.wait('@autocomplete');

  cy.get('[role="option"]').contains(input).should('be.visible').click();
});
