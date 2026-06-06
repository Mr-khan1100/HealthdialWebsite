<!-- Table -->
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <!-- ... (Same THEAD as before) ... -->
        <tbody class="divide-y divide-gray-200">
            <?php if($listings_query->num_rows > 0): ?>
                <?php while($row = $listings_query->fetch_assoc()): ?>
                <tr>
                    <td class="px-4 py-3">#<?php echo $row['id']; ?></td>
                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td class="px-4 py-3"><?php echo $row['category_name']; ?></td>
                    <td class="px-4 py-3"><?php echo $row['status']; ?></td>
                    <td class="px-4 py-3">
                        <div class="flex space-x-2">
                            <a href="?action=status&id=<?php echo $row['id']; ?>&status=approved" class="action-btn text-green-600"><i class="fas fa-check"></i></a>
                            <a href="?action=delete&id=<?php echo $row['id']; ?>" class="action-btn delete-btn text-red-600"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7" class="p-10 text-center text-gray-500">No results found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination UI -->
<?php if($total_pages > 1): ?>
<div class="flex items-center justify-between p-4 border-t">
    <div class="text-sm text-gray-500">
        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_listings); ?> of <?php echo $total_listings; ?>
    </div>
    <div class="flex space-x-1">
        <?php
        $query_string = http_build_query(['category' => $category, 'status' => $status, 'search' => $search]);
        for($i=1; $i<=$total_pages; $i++): 
        ?>
            <a href="?page=<?php echo $i; ?>&<?php echo $query_string; ?>" 
               class="pagination-link px-3 py-1 border rounded <?php echo $i == $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>