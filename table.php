<?php 
    $data = $_POST['data'];
    $response_data = json_decode($data, true);
    // print_r($_POST['data']['message']);
    // echo '<br/>';
    // echo '<br/>';
    // echo '<br/>';
//if(!empty($_POST['data']['message'])) {  
  if(!empty($response_data['message'])) { ?>

<table id="example" class="table table-striped table-bordered dataTable" cellspacing="0" width="100%" role="grid" aria-describedby="example_info" style="width: 100%;">
  <thead>
    <tr role="row">
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending">Sl No.</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending">Offer Name</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Name: activate to sort column ascending">Slug Name</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Position: activate to sort column ascending">Tag</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Office: activate to sort column ascending">Note</th>
      <th class="sorting_desc" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" aria-sort="descending">Networks</th>
      <th class="sorting_desc" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Age: activate to sort column ascending" aria-sort="descending">Clicks</th>
      <th class="sorting" tabindex="0" aria-controls="example" rowspan="1" colspan="1" aria-label="Salary: activate to sort column ascending">Settings</th>
    </tr>
  </thead>
    <tbody id="table_body">
    <?php 
    // print_r($_POST['data']['message']);
      // if(!empty($_POST['data']['message'])) { 
        foreach ($response_data['message'] as $key =>$value) { 
          // echo '<pre>';print_r($value);
          ?>

        <tr>
          <th scope="row"><?= ($key+1);  ?></th>
          <td><?= wordwrap($value['offer'], 10, '<br />', true) ?></td>
          <td><?= wordwrap($value['slug_name'], 10, '<br />', true) ?></td>
          <td><?= wordwrap($value['tag_name'], 10, '<br />', true) ?></td>
          <td><?= wordwrap($value['note'], 10, '<br />', true) ?></td>
          <td><?= wordwrap($value['network_name'], 10, '<br />', true) ?></td>
          <td><?= $value['clicks'] ?></td>
          <td><button data-tooltip="Settings" type="button" value="<?= $value['id'] ?>" class="sting_icon_btn setting cmn_icon" data-bs-toggle="modal"
                                data-bs-target="#exampleModal">
                  <span class="sting_icon">
            <img src="assets/images/setting.png" alt="">
            
          </span>
                </button>
                <button data-tooltip="View Report" type="button" value="<?= $value['id'] ?>" class="view_icon_btn btn_report cmn_icon" data-bs-toggle="modal" data-bs-target="#clickexampleModal">
                  <span class="sting_icon">
            <img src="assets/images/view.png" alt="">
            
          </span>
                </button>
                <button data-tooltip="Archive" type="button" value="<?= $value['id'] ?>" value="<?= $value['id'] ?>" class="btn_archive cmn_icon">
                  <span class="sting_icon">
            <img src="assets/images/archive.png" alt="">
            
          </span>
                </button>

                <button data-tooltip="Delete" type="button" value="<?= $value['id'] ?>" value="<?= $value['id'] ?>" class="btn_delete cmn_icon">
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
      pageLength: 100,
      autoWidth: true
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