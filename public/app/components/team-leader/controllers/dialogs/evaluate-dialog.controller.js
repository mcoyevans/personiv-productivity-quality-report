teamLeaderModule
	.controller('evaluateDialogController', ['$scope', '$mdDialog', '$filter', 'Preloader', 'Report', 'Performance', 'Project', 'Experience', 'Programme', 'Department', 'Member', 'Position', function($scope, $mdDialog, $filter, Preloader, Report, Performance, Project, Experience, Programme, Department, Member, Position){
		$scope.details = {};
		$scope.details.date_start = new Date();
		$scope.details.date_end = new Date();
		$scope.maxDate = new Date();

		Project.index()
			.success(function(data){
				$scope.projects = data;
			})

		Programme.index()
			.success(function(data){
				$scope.work_hours = data;
			})
			.error(function(){
				Preloader.error();
			})

		Member.index()
			.success(function(data){
				$scope.members = data;
			})

		$scope.getPositions = function(){
			if($scope.details.project == 'all'){
				Position.unique()
					.success(function(data){
						$scope.positions = data;
					})
			}
			else{
				Project.show($scope.details.project)
					.success(function(data){
						$scope.positions = data.positions;
					})
			}
		}

		// $scope.hours = [7.5, 8.3, 9.1];

		$scope.months = [
			{'value': '01', 'month': 'January'},
			{'value': '02', 'month': 'February'},
			{'value': '03', 'month': 'March'},
			{'value': '04', 'month': 'April'},
			{'value': '05', 'month': 'May'},
			{'value': '06', 'month': 'June'},
			{'value': '07', 'month': 'July'},
			{'value': '08', 'month': 'August'},
			{'value': '09', 'month': 'September'},
			{'value': '10', 'month': 'October'},
			{'value': '11', 'month': 'November'},
			{'value': '12', 'month': 'December'},
		];

		$scope.months_array = [
			'January',
			'February',
			'March',
			'April',
			'May',
			'June',
			'July',
			'August',
			'September',
			'October',
			'November',
			'December',
		];

		$scope.years = [];
		
		var dateCreated = 2016;

		// will generate the dates that will be used in drop down menu
		for (var i = new Date().getFullYear(); i >= dateCreated; i--) {
			$scope.years.push(i);
		};

		$scope.details.date_start_month = $scope.months_array[new Date().getMonth()];
		$scope.details.date_start_year = $scope.years[0];

		$scope.cancel = function(){
			$mdDialog.cancel();
		};

		$scope.submit = function(){
			if($scope.performanceEvaluationForm.$invalid){
				$scope.showErrors = true;
				angular.forEach($scope.performanceEvaluationForm.$error, function(field){
					angular.forEach(field, function(errorField){
						errorField.$setTouched();
					});
				});
			}
			else{
				$scope.details.date_start = $scope.details.date_start.toDateString();
				$scope.details.date_end = $scope.details.date_end.toDateString();

				if($scope.details.project == 'all'){
					Performance.evaluationMultiple($scope.details.date_start, $scope.details.date_end, $scope.details.daily_work_hours, $scope.details.department, $scope.details.position, $scope.details.member)
						.success(function(data){
							data.multiple = true;
							Preloader.stop(data);
						})
						.error(function(){
							Preloader.error();
						})
				}

				else{
					Performance.evaluation($scope.details.date_start, $scope.details.date_end, $scope.details.daily_work_hours, $scope.details.department, $scope.details.project, $scope.details.position, $scope.details.member)
						.success(function(data){
							Preloader.stop(data);
						})
						.error(function(){
							Preloader.error();
						})
				}
			}
		}
	}]);