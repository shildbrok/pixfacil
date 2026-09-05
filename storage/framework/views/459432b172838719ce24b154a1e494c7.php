<div
    <?php echo e($attributes
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)); ?>

>
    <?php echo e($getChildComponentContainer()); ?>

</div>
<?php /**PATH /home/u187586491/domains/pixfacil.fun/public_html/vendor/filament/forms/resources/views/components/group.blade.php ENDPATH**/ ?>