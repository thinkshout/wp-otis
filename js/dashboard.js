(function () {
	new Vue({
		el: document.getElementById("otis-dashboard-mount"),
		template: `
      <div class="otis-dashboard">
        <h1>OTIS Dashboard</h1>
        <div class="otis-dashboard__settings">
          <div class="input-text-wrap">
            <label for="modified-date">Import Modified POIs Since Date</label>
            <input id="modified-date" type="text" name="otis-modified-date" placeholder="YYYY-MM-DD" v-model="fromDate" />
          </div>
        </div>
        <div class="otis-dashboard__buttons">
          <button class="button button-primary" @click="triggerImport">
            Start
            <span v-if="fromDate">Modified POIs Import</span>
            <span v-else>Bulk Import</span>
          </button>
        </div>
      </div>
    `,
		data: {
			fromDate: "",
		},
		methods: {
			triggerImport() {
				if (
					this.fromDate &&
					this.fromDate.match(
						/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/g
					)
				) {
					console.log(this.fromDate);
				} else {
					console.log("No match");
				}
			},
		},
	});
})();
