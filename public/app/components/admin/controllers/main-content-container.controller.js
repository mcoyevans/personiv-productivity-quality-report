adminModule
	.controller('mainContentContainerController', ['$scope', '$state', '$stateParams', '$mdDialog', 'Preloader', 'Report', 'User', 'Target', 'Programme', 'Department', function($scope, $state, $stateParams, $mdDialog, Preloader, Report, User, Target, Programme, Department){
		$scope.report = {};
		$scope.months = [
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

		var dateCreated = 2016;

		$scope.years = [];

		for (var i = new Date().getFullYear(); i >= dateCreated; i--) {
			$scope.years.push(i);
		};

		Programme.index()
			.success(function(data){
				$scope.work_hours = data;
			});

		Department.index()
			.success(function(data){
				$scope.departments = data;
			})

		$scope.report.month = $scope.months[new Date().getMonth()];
		$scope.report.year = new Date().getFullYear();

		/**
		 * Object for toolbar
		 *
		*/
		$scope.toolbar = {};
		$scope.toolbar.childState = 'Dashboard';
		$scope.toolbar.hideSearchIcon = true;
		/**
		 * Object for subheader
		 *
		*/
		$scope.subheader = {};
		$scope.subheader.show = true;
		$scope.subheader.state = 'dashboard';

		/* Refreshes the list */
		$scope.subheader.refresh = function(){
			$scope.searchMonthlyReport();
		};

		$scope.subheader.download = function(){
			$mdDialog.show({
		    	controller: 'downloadReportDialogController',
		      	templateUrl: '/app/components/admin/templates/dialogs/download-report-dialog.template.html',
		      	parent: angular.element(document.body),
		    });
		}

		$scope.subheader.evaluate = function(){
			$mdDialog.show({
		    	controller: 'evaluateDialogController',
		      	templateUrl: '/app/components/admin/templates/dialogs/evaluate-dialog.template.html',
		      	parent: angular.element(document.body),
		    })
		    .then(function(data){
		    	Preloader.set(data);
		    	
		    	if(!data.department)
		    	{
					$mdDialog.show({
				    	controller: 'performanceEvaluationDialogController',
				      	templateUrl: '/app/shared/templates/dialogs/performance-evaluation.dialog.template.html',
				      	parent: angular.element(document.body),
				    });
		    	}
		    	else{
		    		$mdDialog.show({
				    	controller: 'performanceEvaluationDialogController',
				      	templateUrl: '/app/shared/templates/dialogs/performance-evaluation-multiple.dialog.template.html',
				      	parent: angular.element(document.body),
				    });	
		    	}
		    });
		}

		/**
		 * Status of search bar.
		 *
		*/
		$scope.searchBar = false;

		/**
		 * Reveals the search bar.
		 *
		*/
		$scope.showSearchBar = function(){
			$scope.searchBar = true;
		};

		/**
		 * Hides the search bar.
		 *
		*/
		$scope.hideSearchBar = function(){
			$scope.toolbar.userInput = '';
			$scope.searchBar = false;
		};
		
		
		$scope.searchUserInput = function(){
			$scope.report.show = false;
		};

		/**
		 * Object for content view
		 *
		*/
		$scope.fab = {};

		// $scope.fab.icon = 'mdi-plus';
		// $scope.fab.label = 'Add';
		
		$scope.fab.show = false;

		// $scope.fab.action = function(){
		// 	return;
		// };

		// $scope.rightSidenav = {};

		// $scope.rightSidenav.show = true;

		// $scope.init = function(refresh){
		// 	Report.monthly()
		// 		.success(function(data){
		// 			angular.forEach(data, function(report){
		// 				report.chart = {};
		// 				report.chart.series = ['Productivity', 'Quality'];
		// 				report.chart.data = [[],[]];
		// 				report.chart.labels = [];

		// 				report.date_start = new Date(report.date_start);
		// 				report.count = 0;
		// 				angular.forEach(report.positions, function(position){
		// 					if(position.head_count){
		// 						report.count += position.head_count;
		// 					}
		// 				});
						
		// 				angular.forEach(report.members, function(member){
		// 					member.full_name = member.member.full_name;
		// 					// if(!member.member.deleted_at && member.average_productivity && member.average_quality){
		// 					// 	if(member.roles > 1){
		// 					// 		report.count++;
		// 					// 	}

		// 					// 	report.count++;
		// 					// }
		// 					if(member.average_productivity && member.average_productivity){
		// 						report.chart.data[0].push(member.average_productivity);
		// 						report.chart.data[1].push(member.average_quality);
		// 						report.chart.labels.push(member.member.full_name);
		// 					}
		// 				});
		// 			});
					
		// 			$scope.reports = data;

		// 			if(refresh){
		// 				Preloader.stop();
		// 				Preloader.stop();
		// 			}
		// 		})
		// 		.error(function(){
		// 			Preloader.error();
		// 		})
		// }


		$scope.form = {};

		$scope.searchMonthlyReport = function(){
			if($scope.form.searchMonthlyForm.$invalid){
				angular.forEach($scope.form.searchMonthlyForm.$error, function(field){
					angular.forEach(field, function(errorField){
						errorField.$setTouched();
					});
				});

				return;
			}
			else{

				/* Starts Preloader */
				Preloader.preload();
				/**
				 * Stores Single Record
				*/
				Report.searchMonthly($scope.report)
					.success(function(data){
						angular.forEach(data, function(report){
							report.count = 0;
							
							angular.forEach(report.positions, function(position){
								if(position.head_count){
									report.count += position.head_count;
								}
							});
						});
						
						$scope.reports = data;
						Preloader.stop();
					})
					.error(function(){
						Preloader.error();
					})
			}
		}

		$scope.view = function(data, dateStart, dateEnd){
			data.date_start = dateStart;
			data.date_end = dateEnd;
			Preloader.set(data);
			$mdDialog.show({
		    	controller: 'performanceMonthlyViewDialogController',
		      	templateUrl: '/app/components/admin/templates/dialogs/performance-monthly-view.dialog.template.html',
		      	parent: angular.element(document.body),
		      	clickOutsideToClose:true,
		    });
		}

		// $scope.init();
	}]);