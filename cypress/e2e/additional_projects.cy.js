describe('Tests additional projects and version constraints', () => {
  before(() => {
    // The autocomplete no longer imports projects on its own.
    ['password_policy', 'password_policy_pwned'].forEach((name) => {
      cy.request('POST', '/simplytest/projects/lookup', { name });
    });
  });

  beforeEach(() => {
    cy.visit('/');
  });

  it('should restrict incompatible additional project releases', function () {
    cy.pickProject('Password Policy');
    cy.getByLabel('Version').should('have.value', '4.0.3');
    cy.toggleAdvancedOptions();
    cy.get('button').contains('Add another project').click();
    cy.get('#additional_project_0')
      .getByLabel('Additional project name')
      .type('Password Policy')
      .wait(100)
      .type(' Pwned')
      .wait(2000)
      .type('{downArrow}{enter}');

    cy.wait(400);
    cy.get('#additional_project_0 select').should('have.value', '2.0.1');
  });

  // Regression test for #3571405: the patch field used to stay hidden until
  // a second project was added, because onChange mutated the additional
  // projects array in place instead of copying it.
  it('should show a patch field as soon as an additional project is selected', function () {
    cy.pickProject('Password Policy');
    cy.toggleAdvancedOptions();
    cy.get('button').contains('Add another project').click();
    cy.get('#additional_project_0')
      .getByLabel('Additional project name')
      .type('Password Policy')
      .wait(100)
      .type(' Pwned')
      .wait(2000)
      .type('{downArrow}{enter}');

    cy.wait(400);
    cy.get('#additional_project_0 input[type=url]')
      .should('exist')
      .and('have.value', '');
  });
  // Changing an additional project's version used to reset its patches,
  // because onChange rebuilt the row with `patches: []`. The root project's
  // patches survive a version change, so these should too.
  it('should keep patches on an additional project when its version changes', function () {
    const patchUrl = 'https://www.drupal.org/files/issues/example.patch';

    cy.pickProject('Password Policy');
    cy.toggleAdvancedOptions();
    cy.get('button').contains('Add another project').click();
    cy.get('#additional_project_0')
      .getByLabel('Additional project name')
      .type('Password Policy')
      .wait(100)
      .type(' Pwned')
      .wait(2000)
      .type('{downArrow}{enter}');
    cy.wait(400);

    cy.get('#additional_project_0 input[type=url]').type(patchUrl);

    // Switch to any other release the project offers.
    cy.get('#additional_project_0 select').then(($select) => {
      const current = $select.val();
      const other = [...$select[0].options]
        .map((option) => option.value)
        .find((value) => value && value !== current);
      expect(other, 'a second release to switch to').to.be.a('string');
      cy.get('#additional_project_0 select').select(other);
    });
    cy.wait(400);

    cy.get('#additional_project_0 input[type=url]').should(
      'have.value',
      patchUrl,
    );
  });

  // The patch field ids were hardcoded, so the root project and every
  // additional project all rendered project_patch_url_0. The additional
  // project's sr-only label pointed `for` at the root project's input.
  it('should give every patch field a unique id its own label points at', function () {
    cy.pickProject('Password Policy');
    cy.toggleAdvancedOptions();
    cy.get('button').contains('Add another project').click();
    cy.get('#additional_project_0')
      .getByLabel('Additional project name')
      .type('Password Policy')
      .wait(100)
      .type(' Pwned')
      .wait(2000)
      .type('{downArrow}{enter}');
    cy.wait(400);

    cy.get('input[type=url]').then(($inputs) => {
      const ids = [...$inputs].map((input) => input.id);
      expect(ids, 'every patch input has an id')
        .to.have.length(2)
        .and.to.not.include('');
      expect(new Set(ids).size, 'distinct patch input ids').to.eq(2);
      ids.forEach((id) => {
        cy.get(`[id="${id}"]`).should('have.length', 1);
        cy.get(`label[for="${id}"]`).should('have.length', 1);
      });
    });
  });

  // #3494635: the format hint is rendered once per Patches instance, so the
  // root project and each additional project must not share an id.
  it('should give each patch field its own format hint', function () {
    cy.pickProject('Password Policy');
    cy.toggleAdvancedOptions();
    cy.get('button').contains('Add another project').click();
    cy.get('#additional_project_0')
      .getByLabel('Additional project name')
      .type('Password Policy')
      .wait(100)
      .type(' Pwned')
      .wait(2000)
      .type('{downArrow}{enter}');
    cy.wait(400);

    cy.get('input[type=url]').then(($inputs) => {
      const hintIds = [...$inputs].map((input) =>
        input.getAttribute('aria-describedby'),
      );
      expect(hintIds).to.have.length(2);
      expect(new Set(hintIds).size, 'distinct hint ids').to.eq(2);
      hintIds.forEach((hintId) => {
        cy.get(`[id="${hintId}"]`).should('have.length', 1).and('be.visible');
      });
    });
  });

  // Rows used to key on the shortname, which is "" until a project is picked,
  // so two empty rows shared a key. React tolerates that here because the row
  // ids come from the map index, so this does not reproduce a visible failure.
  // It guards the arrangement the stable row id is meant to protect.
  it('should render two separate rows when adding two projects before picking either', function () {
    cy.pickProject('Password Policy');
    cy.toggleAdvancedOptions();
    cy.get('button').contains('Add another project').click();
    cy.get('button').contains('Add another project').click();

    cy.get('[id^="additional_project_"]').should('have.length', 2);
    cy.get('#additional_project_0').should('exist');
    cy.get('#additional_project_1').should('exist');
  });

  // The row id is a UI concern. The backend builds typed data from this
  // payload and throws on properties it does not define, so this guards the
  // strip in getLaunchPayload rather than reproducing an older bug.
  it('should not send the internal row id in the launch payload', function () {
    cy.intercept('POST', '**/launch-project', {
      statusCode: 503,
      body: { message: 'stubbed, not launching' },
    }).as('launch');

    cy.pickProject('Password Policy');
    cy.toggleAdvancedOptions();
    cy.get('button').contains('Add another project').click();
    cy.get('#additional_project_0')
      .getByLabel('Additional project name')
      .type('Password Policy')
      .wait(100)
      .type(' Pwned')
      .wait(2000)
      .type('{downArrow}{enter}');
    cy.wait(400);

    cy.get('button').contains('Launch sandbox').click();

    cy.wait('@launch')
      .its('request.body.additionalProjects')
      .should((projects) => {
        expect(projects).to.have.length(1);
        expect(projects[0]).to.not.have.property('id');
        expect(projects[0].shortname).to.eq('password_policy_pwned');
      });
  });
});
