adminModule
	.controller('positionsContentContainerController', ['$scope', '$state', '$stateParams', '$mdDialog', 'Department', 'Preloader', 'Project', 'Position', function($scope, $state, $stateParams, $mdDialog, Department, Preloader, Project, Position){
		/**
		 * Object for toolbar
		 *
		*/
		var department_id = $stateParams.departmentID;
		var project_id = $stateParams.projectID;
		
		$scope.position = {};

		$scope.toolbar = {};
		$scope.toolbar.showBack = true;
		$scope.toolbar.back = function(){
			$state.go('main.projects', {'departmentID':department_id});
		}
		
		Department.show(department_id)
			.success(function(data){
				$scope.toolbar.parentState = data.name;
			});

		Project.show(project_id)
			.success(function(data){
				$scope.toolbar.childState = data.name;
			})
		/**
		 * Object for subheader
		 *
		*/
		$scope.subheader = {};
		$scope.subheader.state = 'positions';

		$scope.subheader.refresh = function(){
			Preloader.preload();
			$scope.init(true);
		}

		/**
		 * Object for content view
		 *
		*/
		$scope.fab = {};

		$scope.fab.icon = 'mdi-plus';
		$scope.fab.label = 'Position';
		
		$scope.fab.show = true;

		$scope.fab.action = function(){
			$mdDialog.show({
		    	controller: 'addPositionDialogController',
		      	templateUrl: '/app/components/admin/templates/dialogs/add-position.dialog.template.html',
		      	parent: angular.element(document.body),
		    })
		    .then(function(){
		    	$scope.subheader.refresh();
		    })
		};

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
			$scope.toolbar.searchText = '';
			$scope.searchBar = false;
		};
		
		
		$scope.searchUserInput = function(){
			$scope.project.show = false;
			Preloader.preload()
			Position.search($scope.toolbar)
				.success(function(data){
					$scope.project.results = data;
					Preloader.stop();
				})
				.error(function(data){
					Preloader.error();
				});
		};

		$scope.viewProject = function(id){
			$state.go('main.positions', {'departmentID':department_id, 'projectID':id});
		}

		$scope.edit = function(id){
			Preloader.set(id);
			$mdDialog.show({
		    	controller: 'editPositionDialogController',
		      	templateUrl: '/app/components/admin/templates/dialogs/add-position.dialog.template.html',
		      	parent: angular.element(document.body),
		    })
		    .then(function(){
		    	$scope.subheader.refresh();
		    });
		}

		$scope.init = function(refresh){
			$scope.position = {};
			Position.project(project_id)
				.success(function(data){
					angular.forEach(data, function(item){
						item.first_letter = item.name.charAt(0).toUpperCase();
						item.created_at = new Date(item.created_at);
					});

					$scope.position.all = data;
					$scope.position.show = true;

					if(refresh){
						Preloader.stop();
						Preloader.stop();
					}
				})
				.error(function(){
					Preloader.error();
				});
		}

		$scope.init();
	}])