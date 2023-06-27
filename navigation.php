

    <!-- Sidebar -->

    <div class="sidebar">

      <!-- Sidebar user panel (optional) -->

      <div class="user-panel mt-3 pb-3 mb-3 d-flex">

        <div class="image">

          <img src="dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">

        </div>

        <div class="info">

          <a href="#" class="d-block"><?php echo $_SESSION['fullname']; ?></a>

        </div>

      </div>



      <!-- SidebarSearch Form -->

      <div class="form-inline">

        <div class="input-group" data-widget="sidebar-search">

          <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">

          <div class="input-group-append">

            <button class="btn btn-sidebar">

              <i class="fas fa-search fa-fw"></i>

            </button>

          </div>

        </div>

      </div>



      <!-- Sidebar Menu -->

      <nav class="mt-2">

        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

          <!-- Add icons to the links using the .nav-icon class

               with font-awesome or any other icon font library -->

          <li class="nav-item menu-open">

            <a href="#" class="nav-link">

              <i class="nav-icon fas fa-tachometer-alt"></i>

              <p>

                Dashboard

                <i class="right fas fa-angle-left"></i>

              </p>

            </a>

            <ul class="nav nav-treeview">

              <li class="nav-item">

                <a href="./index.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Dashboard v1</p>

                </a>

              </li>

<!--

              <li class="nav-item">

                <a href="./index2.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Dashboard v2</p>

                </a>

              </li>

              <li class="nav-item">

                <a href="./index3.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Dashboard v3</p>

                </a>

              </li>

-->

            </ul>

          </li>



          <li class="nav-item menu-open">

            <a href="#" class="nav-link">

              <i class="nav-icon fas fa-user-alt"></i>

              <p>

                Subscriber

                <i class="right fas fa-angle-left"></i>

              </p>

            </a>

            <ul class="nav nav-treeview">

              <li class="nav-item">

                <a href="pages/forms/sub.addnew.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Add Subscriber</p>

                </a>

              </li>

         



              <li class="nav-item">

                <a href="pages/tables/collections.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Post Payment</p>

                </a>

              </li>

              <li class="nav-item">

                <a href="pages/tables/allsubs.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Advance Payment</p>

                </a>

              </li>



              <li class="nav-item">

                <a  href="pages/tables/reports.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>List of Subscribers</p>

                </a>

              </li>

              <li class="nav-item">

                <a href="pages/tables/collections_revert.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Revert Payment</p>

                </a>

              </li>



              <li class="nav-item">

                <a href="pages/tables/collections_revert_approval.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Approve Revert Payment</p>

                </a>

              </li>



              <li class="nav-item">

                <a href="pages/tables/collectibles_entry.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Collectibles Entry</p>

                </a>

              </li>

              <li class="nav-item">

<a href="pages/tables/remittances.php" class="nav-link">

  <i class="far fa-circle nav-icon"></i>

  <p>Remittances</p>

</a>

</li>

            </ul>

          </li>





          <li class="nav-item menu-open">

            <a href="#" class="nav-link">

              <i class="nav-icon fas fa-list-alt"></i>

              <p>

                Collection Report

                <i class="right fas fa-angle-left"></i>

              </p>

            </a>

            <ul class="nav nav-treeview">

            

            <li class="nav-item">

                <a href="pages/tables/collections_posted.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>My Collections</p>

                </a>

              </li>



              <li class="nav-item">

                <a href="pages/tables/collections_paid.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Current Month - Paid</p>

                </a>

              </li>

              <li class="nav-item">

                <a href="pages/tables/collections_unpaid.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Current Month - Unpaid</p>

                </a>

              </li>



              <li class="nav-item">

                <a href="pages/tables/collections_unpaid_all.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Overdue Backlogs</p>

                </a>

              </li>





           



              <li class="nav-item">

                <a href="pages/tables/collections_monthly.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Collection (Monthly)</p>

                </a>

              </li>



              <li class="nav-item">

                <a href="pages/tables/collections_15d.php" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Filtered Reports</p>

                </a>

              </li>

          

            </ul>

          </li>







          <li class="nav-item menu-open">

            <a href="#" class="nav-link">

            <i class="nav-icon fas fa-chart-pie"></i>

              <p>

               Visuals

                <i class="right fas fa-angle-left"></i>

              </p>

            </a>

            <ul class="nav nav-treeview">

<!--

            <li class="nav-item">

            <a href="#" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>15d / Month Collection</p>

                </a>

              </li>

          



              <li class="nav-item">

              <a href="#" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Monthly Collection</p>

                </a>

              </li>



              

              <li class="nav-item">

                <a href="#" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>All Time</p>

                </a>

              </li>

-->



              <li class="nav-item">

                <a href="pages/calendar.html" class="nav-link">

                  <i class="far fa-circle nav-icon"></i>

                  <p>Calendar</p>   <span class="badge badge-info right">2</span>

                </a>

              </li>

            </ul>

          </li>

          

            </ul>

          </li>

         

      

       

         

          

      </nav>

      <!-- /.sidebar-menu -->