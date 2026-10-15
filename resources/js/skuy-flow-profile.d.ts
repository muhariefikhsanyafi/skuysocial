export type ProfilePostMenuPlacementInput = {
    triggerTop: number;
    triggerBottom: number;
    menuHeight: number;
    safeBottom: number;
};

export type ProfilePostMenuPlacement = {
    placement: 'above' | 'below';
    availableHeight: number;
    needsScroll: boolean;
};

export function chooseProfilePostMenuPlacement(
    input: ProfilePostMenuPlacementInput,
): ProfilePostMenuPlacement;
