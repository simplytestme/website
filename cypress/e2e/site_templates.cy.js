describe('The site template picker', function () {
  beforeEach(() => {
    cy.intercept('GET', '**/one-click-demos', {
      fixture: 'launch_form/one_click_demos.json',
    });
    cy.intercept('GET', '**/site-templates', {
      fixture: 'launch_form/site_templates.json',
    });
    cy.visit('/');
  });

  const openPicker = () => {
    cy.contains('button', 'Browse templates').click();
    return cy.get('[role="dialog"]');
  };

  it('shows how many templates there are before opening', () => {
    cy.contains('3 templates').should('be.visible');
    cy.contains('button', 'Browse templates').should('not.be.disabled');
  });

  it('lists every template as a card', () => {
    openPicker().within(() => {
      cy.contains('h2', 'Pick a starting point').should('be.visible');
      cy.contains('h3', 'Byte').should('be.visible');
      cy.contains('h3', 'Haven').should('be.visible');
      cy.contains('h3', 'Healthcare').should('be.visible');
      // The creator is shown because the curated list has no recipe count.
      cy.contains('Kanopi Studios').should('be.visible');
    });
  });

  it('filters the cards as you type', () => {
    openPicker().within(() => {
      cy.get('#site-template-filter').type('non-profit');
      cy.contains('h3', 'Haven').should('be.visible');
      cy.contains('h3', 'Byte').should('not.exist');

      // Matching on the creator as well as the name and description.
      cy.get('#site-template-filter').clear();
      cy.get('#site-template-filter').type('Kanopi');
      cy.contains('h3', 'Healthcare').should('be.visible');
      cy.contains('h3', 'Haven').should('not.exist');

      cy.get('#site-template-filter').clear();
      cy.get('#site-template-filter').type('nothing matches this');
      cy.contains('No templates match').should('be.visible');
    });
  });

  it('closes on Escape, the close button and the scrim', () => {
    openPicker();
    cy.get('body').type('{esc}');
    cy.get('[role="dialog"]').should('not.exist');

    openPicker().within(() => {
      cy.get('button[aria-label="Close"]').click();
    });
    cy.get('[role="dialog"]').should('not.exist');

    openPicker();
    // The scrim is the dialog's parent; clicking the dialog must not close it.
    cy.get('[role="dialog"]').click('topLeft');
    cy.get('[role="dialog"]').should('exist');
    cy.get('[role="presentation"]').click('topLeft');
    cy.get('[role="dialog"]').should('not.exist');
  });

  it('launches a template through the one click demo endpoint', () => {
    cy.intercept('POST', '**/one-click-demos/site_template%3Abyte', {
      statusCode: 200,
      body: {
        status: 'OK',
        progress: 'http://localhost:8080/tugboat/progress/1/2',
      },
    }).as('launch');

    openPicker().within(() => {
      cy.contains('h3', 'Byte')
        .parents('div')
        .first()
        .contains('button', 'Launch')
        .click();
    });

    cy.wait('@launch');
    cy.location('pathname').should('eq', '/tugboat/progress/1/2');
    cy.location('search').should('contain', 'title=Byte');
  });
});
