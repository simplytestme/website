describe('Core versions install when Simplytest is installed', function () {
  it('has core versions available after install', () => {
    ;[7, 8, 9].forEach((version) => {
      cy.request('simplytest/core/versions/' + version)
        .should(response => {
          expect(response.status).to.eq(200)
          expect(response.body.list).to.have.length.gt(0)
        })
    })
  })
})
