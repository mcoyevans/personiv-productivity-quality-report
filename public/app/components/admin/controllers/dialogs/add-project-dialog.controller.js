adminModule
	.controller('addProjectDialogController', ['$scope', '$mdDialog', 'Preloader', 'Department', 'Project', function($scope, $mdDialog, Preloader, Department, Project){
		var departmentID = Preloader.get();

		$scope.project = {};
		$scope.project.department_id = departmentID;

		Department.show(departmentID)
			.success(function(data){
				$scope.department = data;
			});

		$scope.cancel = function(){
			$mdDialog.cancel();
		}

		$scope.submit = function(){
			if($scope.addProjectForm.$invalid){
				angular.forEach($scope.addProjectForm.$error, function(field){
					angular.forEach(field, function(errorField){
						errorField.$setTouched();
					});
				});
			}
			else{
				/* Starts Preloader */
				Preloader.preload();
				/**
				 * Stores Single Record
				*/
				Project.store($scope.project)
					.success(function(){
						// Preloader.stop();
					})
					.error(function(){
						Preloader.error();
					});
			}
		}
	}]);