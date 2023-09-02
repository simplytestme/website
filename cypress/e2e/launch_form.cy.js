describe('Test the launch form', function () {
  beforeEach(() => {
    cy.visit('/')
  })
  it('allows autocompleting of a project with a version selected', () => {
    cy.pickProject('Password Policy')
    cy.getByLabel('Project version')
      .should('have.value', '4.0.0')
  })
  it('with a project and modify the drupal core version', () => {
    cy.pickProject('Password Policy')
    cy.getByLabel('Project version')
      .should('have.value', '4.0.0')
    cy.toggleDetailsElement('Advanced options')
    cy.getByLabel('Drupal Core')
      .select('9.5.9')
  })
  it('should allow me to attach a patch to my project', function () {
    cy.pickProject('Pathauto')
    cy.toggleDetailsElement('Advanced options')
    cy.getByLabel('Project patch 0')
      .type('https://www.drupal.org/files/issues/2020-12-07/3185080-3.patch')
    cy.wait(200);
    cy.get('button').contains('Add patch').click();
    cy.getByLabel('Project patch 1');
    cy.get('#project_patch_1').get('button').contains('×').click();
    cy.get('#project_patch_0').get('button').contains('×').click();
  })
  it('should adjust available core versions based on compatibility', function () {
    cy.pickProject('Pathauto')
    cy.getByLabel('Project version')
      .should('have.value', '8.x-1.11')
    cy.toggleDetailsElement('Advanced options')
    cy.getByLabel('Drupal Core')
      .should('have.value', '10.1.1')
    cy.getByLabel('Project version')
      .select('8.x-1.6')
    cy.getByLabel('Drupal Core')
      .should('have.value', '8.9.19')
    cy.getByLabel('Project version')
      .select('8.x-1.11')
    cy.getByLabel('Drupal Core')
      .select('9.5.0')
  })
  it('should show the Umami demo for Drupal 8.6.x and Drupal 9 sites', function () {
    cy.pickProject('Pathauto')
    cy.getByLabel('Project version')
      .should('have.value', '8.x-1.11')
    cy.getByLabel('Project version')
      .select('7.x-1.0')
    cy.toggleDetailsElement('Advanced options')

    // Drupal 7 has no Umami.
    cy.getByLabel('Drupal Core')
      .should('have.value', '7.97')
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')

    // Default Drupal 8 has Umami.
    cy.getByLabel('Project version')
      .select('8.x-1.6')
    cy.getByLabel('Drupal Core')
      .should('have.value', '8.9.19')
    cy.wait(100);
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')
    cy.getByLabel('Install profile')
      .contains('Umami Demo')

    // Drupal < 8.6 doesn't have Umami
    cy.getByLabel('Drupal Core')
      .select('8.5.9')
    cy.wait(100);
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')
    cy.getByLabel('Install profile')
      .contains('Umami Demo').should('not.exist')

    // Drupal 9 has Umami.
    cy.getByLabel('Project version')
      .select('8.x-1.11')
    cy.getByLabel('Drupal Core')
      .select('9.5.0')
    cy.wait(100);
    cy.getByLabel('Install profile')
      .contains('Minimal')
    cy.getByLabel('Install profile')
      .contains('Standard')
    cy.getByLabel('Install profile')
      .contains('Umami Demo')
  })
})
