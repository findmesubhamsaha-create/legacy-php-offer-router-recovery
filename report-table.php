<?php if(!empty($_REQUEST['data']['message'])) {  ?>
<table id="exampl-report" class="table table-striped" cellspacing="0" width="100%">
               <thead>
                  <tr>
                     <th>Offer ID</th>
                     <th>Main Offer URL</th>
                     <th>Clicks</th>
                     <th>Report Date</th>
                  </tr>
               </thead>
              
               <tbody>
                  <?php foreach ($_REQUEST['data']['message'] as $key =>$value) { ?>
                     <tr>
                        <td><?= $value['main_offer_id'] ?></td>
                        <td><?= $value['main_offer_url'] ?></td>
                        <td><?= $value['offer_clicks'] ?></td>
                        <td><?= $value['report_date'] ?></td>
                     </tr>
                  <?php } ?>
      </tbody>
</table>
<?php } else{ ?> 
      <p> No data Found</p>
<?php } ?>

<script type="text/javascript">
  $(function () {
    new DataTable('#exampl-report');
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