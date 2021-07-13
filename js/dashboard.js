(function () {
	new Vue({
		el: document.getElementById("otis-dashboard-mount"),
		template: `
      <div class="otis-dashboard">
        <h1>OTIS Dashboard</h1>
      </div>
    `,
		data: {},
		mounted: function () {
			console.log("Hello Vue!");
		},
	});
})();
