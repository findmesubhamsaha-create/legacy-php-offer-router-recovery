<?php if(!empty($_REQUEST['data']['message'])) {  ?>

<table id="example" class="table table-striped table-bordered dataTable" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
  <thead>
    <tr role="row">
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending" style="width: 55px;">Sl No.</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending" style="width: 137px;">Offer Name</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending" style="width: 137px;">Slug Name</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Position: activate to sort column ascending" style="width: 44px;">Tag</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Office: activate to sort column ascending" style="width: 100px;">Note</th>
      <th class="sorting_desc" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" aria-sort="descending" style="width: 44px;">Networks</th>
      <th class="sorting_desc" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" aria-sort="descending" style="width: 90px;">Clicks</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Salary: activate to sort column ascending" style="width: 77px;">Settings</th>
    </tr>
  </thead>
  
    <tbody id="table_body">
    <?php //print_r($_REQUEST['data']['response']);
      // if(!empty($_REQUEST['data']['message'])) { 
        foreach ($_REQUEST['data']['message'] as $key =>$value) { ?>

        <tr>
          <th scope="row"><?= ($key+1);  ?></th>
          <td><?= $value['offer'] ?></td>
          <td><?= $value['slug_name'] ?></td>
          <td><?= $value['tag_id'] ?></td>
          <td><?= $value['note'] ?></td>
          <td><?= $value['network_id'] ?></td>
          <td><?= $value['clicks'] ?></td>
          <td class=""><button data-tooltip="Settings" type="button" value="<?= $value['id'] ?>" class="btn_report" data-bs-toggle="modal"
                                data-bs-target="#exampleModal">
                  <span class="sting_icon">
					  <img src="assets/images/setting.png" alt="">
					  
				  </span>
                </button>
                <button data-tooltip="View Report" type="button" value="<?= $value['id'] ?>" class="btn_report" data-bs-toggle="modal" data-bs-target="#clickexampleModal">
                  <span class="sting_icon">
					  <img src="assets/images/view.png" alt="">
					  
				  </span>
                </button>
                <button data-tooltip="Archive" type="button" value="<?= $value['id'] ?>" class="btn_report" data-bs-toggle="modal" data-bs-target="#clickexampleModal">
                  <span class="sting_icon">
					  <img src="assets/images/archive.png" alt="">
					  
				  </span>
                </button>

                <button data-tooltip="Delete" type="button" value="<?= $value['id'] ?>" class="btn_report" data-bs-toggle="modal" data-bs-target="#clickexampleModal">
                  <span class="sting_icon">
					  <img src="assets/images/delete.png" alt="">
					  
				  </span>
                </button>
            </td>
          </tr>
                        
        <?php } ?>
                   
      </tbody>
</table>

<?php } else{ ?> 
      <p> No data Found</p>
<?php } ?>

<!-- <script type="text/javascript" src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.8/js/jquery.dataTables.min.js"></script> -->
<script type="text/javascript">
  $(function () {
    new DataTable('#example',{
      pageLength: 100
    });
       /* $('#example').dataTable({
          paging: false,
          fixedHeader: {
            header: true
          },
            dom: 'Bfrtip',
            buttons: [
            {
              extend: 'excel',
              text: 'Excel <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>'
            },
            {
              extend: 'pdf',
              text: 'PDF <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>'
            },
               'copy',
               'pdf',
               'colvis'
            ],
          
        }); */
      });
</script>
<style>
button.view_icon_btn{
  margin-left: 10px;
}
button.view_icon_btn.btn_report {
    border: none;
  background:none;
}
</style>